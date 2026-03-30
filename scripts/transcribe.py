#!/usr/bin/env python3
"""Thin wrapper around WhisperX that sets without_timestamps=False.

WhisperX's CLI hard-codes without_timestamps=True, which causes 5-15 s of
audio to be silently dropped (https://github.com/m-bain/whisperX/issues/932).
The option is not exposed as a CLI flag, so this script calls the Python API
directly and writes the same JSON output the CLI would produce.
"""

import argparse
import json
import os
import sys

import numpy as np
import whisperx


def main() -> None:
    parser = argparse.ArgumentParser(description="Transcribe audio with WhisperX")
    parser.add_argument("audio", help="Path to audio/video file")
    parser.add_argument("--model", default="large-v3")
    parser.add_argument("--output_format", default="json")
    parser.add_argument("--output_dir", required=True)
    parser.add_argument("--language", default="en")
    parser.add_argument("--compute_type", default="int8")
    parser.add_argument("--chunk_size", type=int, default=15)
    parser.add_argument("--vad_onset", type=float, default=0.3)
    parser.add_argument("--vad_offset", type=float, default=0.3)
    parser.add_argument("--batch_size", type=int, default=16)
    parser.add_argument("--diarize", action="store_true")
    parser.add_argument("--hf_token", default=None)
    args = parser.parse_args()

    device = "cuda" if _cuda_available() else "cpu"

    asr_options = {
        "without_timestamps": False,
    }

    vad_options = {
        "vad_onset": args.vad_onset,
        "vad_offset": args.vad_offset,
    }

    model = whisperx.load_model(
        args.model,
        device=device,
        compute_type=args.compute_type,
        language=args.language,
        asr_options=asr_options,
        vad_options=vad_options,
    )

    audio = whisperx.load_audio(args.audio)

    result = model.transcribe(
        audio,
        batch_size=args.batch_size,
        language=args.language,
        chunk_size=args.chunk_size,
        print_progress=True,
    )

    # Align whisper output for word-level timestamps
    align_model, align_metadata = whisperx.load_align_model(
        language_code=result["language"],
        device=device,
    )
    result = whisperx.align(
        result["segments"],
        align_model,
        align_metadata,
        audio,
        device,
        return_char_alignments=False,
    )

    # Speaker diarization
    if args.diarize:
        if not args.hf_token:
            print("ERROR: --hf_token is required for diarization", file=sys.stderr)
            sys.exit(1)
        diarize_model = whisperx.DiarizationPipeline(
            use_auth_token=args.hf_token,
            device=device,
        )
        diarize_segments = diarize_model(audio)
        result = whisperx.assign_word_speakers(diarize_segments, result)

    # Write output JSON (same structure as whisperx CLI)
    os.makedirs(args.output_dir, exist_ok=True)
    basename = os.path.splitext(os.path.basename(args.audio))[0]
    output_path = os.path.join(args.output_dir, f"{basename}.json")

    # Convert numpy types to native Python types for JSON serialization
    output = _make_serializable(result)

    with open(output_path, "w") as f:
        json.dump(output, f, indent=2, ensure_ascii=False)

    print(f"Transcript written to {output_path}")


def _cuda_available() -> bool:
    try:
        import torch
        return torch.cuda.is_available()
    except ImportError:
        return False


def _make_serializable(obj):
    """Recursively convert numpy types to native Python types."""
    if isinstance(obj, dict):
        return {k: _make_serializable(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [_make_serializable(v) for v in obj]
    if isinstance(obj, np.integer):
        return int(obj)
    if isinstance(obj, np.floating):
        return float(obj)
    if isinstance(obj, np.ndarray):
        return obj.tolist()
    return obj


if __name__ == "__main__":
    main()
