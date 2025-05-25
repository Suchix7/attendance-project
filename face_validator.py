import cv2
import os
import numpy as np
from pathlib import Path
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def create_output_directory():
    output_dir = Path('validation_output')
    output_dir.mkdir(exist_ok=True)
    return output_dir

def detect_faces(image_path, output_dir, face_cascade):
    # Read the image
    image = cv2.imread(str(image_path))
    if image is None:
        logging.error(f"Error: Could not read image {image_path}")
        return None
    
    # Convert to grayscale and apply histogram equalization
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.equalizeHist(gray)
    
    # Detect faces with optimized parameters from the project
    faces = face_cascade.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=4,
        minSize=(30, 30),
        maxSize=(400, 400)
    )
    
    # Create a copy for drawing
    output_image = image.copy()
    
    # Draw rectangles around faces and add information
    num_faces = len(faces)
    cv2.putText(output_image, f'Faces detected: {num_faces}', (10, 30),
                cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
    
    if num_faces != 1:
        cv2.putText(output_image, 'WARNING: Should have exactly 1 face!', (10, 60),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 0, 255), 2)
    
    # Draw rectangles for each face
    for (x, y, w, h) in faces:
        cv2.rectangle(output_image, (x, y), (x+w, y+h), (0, 255, 0), 2)
    
    # Save the output image
    output_path = output_dir / f'validated_{image_path.name}'
    cv2.imwrite(str(output_path), output_image)
    
    return num_faces

def main():
    # Get the cascade file path from the project
    cascade_path = Path('assets/haarcascade_frontalface_default.xml')
    
    if not cascade_path.exists():
        logging.error(f"Error: Cascade file not found at {cascade_path}")
        return
    
    # Load the cascade classifier
    face_cascade = cv2.CascadeClassifier(str(cascade_path))
    if face_cascade.empty():
        logging.error("Error: Could not load face cascade classifier")
        return
    
    # Create output directory
    output_dir = create_output_directory()
    
    # Process all images in test_images directory
    test_images_dir = Path('test_images')
    
    if not test_images_dir.exists():
        logging.error(f"Error: Test images directory not found at {test_images_dir}")
        return
    
    logging.info("Starting face validation process...")
    
    # Supported image extensions
    image_extensions = ('.jpg', '.jpeg', '.png')
    
    # Results summary
    total_images = 0
    correct_images = 0  # Images with exactly one face
    
    for image_path in test_images_dir.glob('*'):
        if image_path.suffix.lower() in image_extensions:
            total_images += 1
            logging.info(f"\nProcessing {image_path.name}...")
            
            num_faces = detect_faces(image_path, output_dir, face_cascade)
            
            if num_faces is None:
                logging.error(f"Failed to process {image_path.name}")
                continue
                
            if num_faces == 1:
                correct_images += 1
                logging.info(f"✓ Success: Found exactly 1 face in {image_path.name}")
            else:
                logging.warning(f"✗ Warning: Found {num_faces} faces in {image_path.name} (should be 1)")
    
    # Print summary
    logging.info("\n=== Validation Summary ===")
    logging.info(f"Total images processed: {total_images}")
    logging.info(f"Images with exactly one face: {correct_images}")
    if total_images > 0:
        logging.info(f"Success rate: {(correct_images/total_images)*100:.1f}%")
    logging.info(f"\nProcessed images saved in: {output_dir}")

if __name__ == "__main__":
    main() 