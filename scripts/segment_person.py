"""Generate a person segmentation mask from an image using MediaPipe.

Usage:
    python scripts/segment_person.py --input frame.jpg --output mask.png

Produces a grayscale PNG where white (255) = person, black (0) = background.
Uses MediaPipe Selfie Segmentation (general model, 256x256, ~454KB).
"""

import argparse
import sys

import cv2
import mediapipe as mp
import numpy as np


def segment_person(input_path: str, output_path: str, threshold: float = 0.5) -> None:
    image = cv2.imread(input_path)
    if image is None:
        print(f"Error: could not read image at {input_path}", file=sys.stderr)
        sys.exit(1)

    rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

    with mp.solutions.selfie_segmentation.SelfieSegmentation(model_selection=1) as seg:
        result = seg.process(rgb)

    # result.segmentation_mask is a float32 array in [0, 1]
    mask = (result.segmentation_mask > threshold).astype(np.uint8) * 255

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
