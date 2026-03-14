"""Generate a person segmentation mask from an image using MediaPipe.

Usage:
    python scripts/segment_person.py --input frame.jpg --output mask.png

Produces a grayscale PNG where white (255) = person, black (0) = background.
Uses the MediaPipe Image Segmenter Tasks API with the selfie_segmenter model.
"""

import argparse
import sys
from pathlib import Path

import cv2
import mediapipe as mp
import numpy as np

# Resolve the model path relative to this script's location
_SCRIPT_DIR = Path(__file__).resolve().parent
_MODEL_PATH = _SCRIPT_DIR.parent / "models" / "selfie_segmenter.tflite"

BaseOptions = mp.tasks.BaseOptions
ImageSegmenter = mp.tasks.vision.ImageSegmenter
ImageSegmenterOptions = mp.tasks.vision.ImageSegmenterOptions
VisionRunningMode = mp.tasks.vision.RunningMode


def segment_person(input_path: str, output_path: str, threshold: float = 0.5) -> None:
    image = cv2.imread(input_path)
    if image is None:
        print(f"Error: could not read image at {input_path}", file=sys.stderr)
        sys.exit(1)

    if not _MODEL_PATH.exists():
        print(f"Error: model not found at {_MODEL_PATH}", file=sys.stderr)
        sys.exit(1)

    rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
    mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb)

    options = ImageSegmenterOptions(
        base_options=BaseOptions(model_asset_path=str(_MODEL_PATH)),
        running_mode=VisionRunningMode.IMAGE,
        output_confidence_masks=True,
    )

    with ImageSegmenter.create_from_options(options) as segmenter:
        result = segmenter.segment(mp_image)

    # confidence_masks[0] is the person confidence mask (float32, 0-1)
    confidence_mask = result.confidence_masks[0].numpy_view()

    # Threshold to binary and scale to 0-255
    mask = (confidence_mask > threshold).astype(np.uint8) * 255

    # Apply a slight Gaussian blur to soften the mask edges
    mask = cv2.GaussianBlur(mask, (15, 15), sigmaX=5)

    cv2.imwrite(output_path, mask)


def main() -> None:
    parser = argparse.ArgumentParser(description="Segment person from image")
    parser.add_argument("--input", required=True, help="Path to input image")
    parser.add_argument("--output", required=True, help="Path to output mask PNG")
    parser.add_argument(
        "--threshold",
        type=float,
        default=0.5,
        help="Segmentation confidence threshold (0-1, default 0.5)",
    )
    args = parser.parse_args()

    segment_person(args.input, args.output, args.threshold)


if __name__ == "__main__":
    main()
