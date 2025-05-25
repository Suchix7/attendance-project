import cv2
import os
import shutil
from pathlib import Path
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def validate_faces():
    """Validate face images in student folders and copy valid ones to a new directory"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        students_dir = base_dir / 'students'
        validated_dir = base_dir / 'validated_faces'
        
        # Create validated faces directory
        validated_dir.mkdir(exist_ok=True)
        
        # Load face cascade
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        if not cascade_path.exists():
            logging.error(f"Cascade file not found at: {cascade_path}")
            return
            
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        if face_cascade.empty():
            logging.error("Failed to load face cascade classifier")
            return
        
        # Process each student directory
        total_images = 0
        valid_images = 0
        
        for student_dir in students_dir.iterdir():
            if not student_dir.is_dir():
                continue
                
            student_id = student_dir.name
            logging.info(f"\nProcessing student {student_id}")
            
            # Create student directory in validated faces
            student_validated_dir = validated_dir / student_id
            student_validated_dir.mkdir(exist_ok=True)
            
            # Process each face image
            for img_path in student_dir.glob('face_*.jpg'):
                total_images += 1
                logging.info(f"Checking image: {img_path.name}")
                
                # Read image
                img = cv2.imread(str(img_path))
                if img is None:
                    logging.warning(f"Failed to load image: {img_path}")
                    continue
                
                # Convert to grayscale and apply histogram equalization
                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                gray = cv2.equalizeHist(gray)
                
                # Detect faces with optimized parameters
                faces = face_cascade.detectMultiScale(
                    gray,
                    scaleFactor=1.1,
                    minNeighbors=4,
                    minSize=(30, 30),
                    maxSize=(400, 400)
                )
                
                # Create debug image
                debug_img = img.copy()
                
                if len(faces) == 1:
                    # Single face detected - good!
                    valid_images += 1
                    x, y, w, h = faces[0]
                    
                    # Draw green rectangle for valid face
                    cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 255, 0), 2)
                    cv2.putText(debug_img, 'Valid Face', (x, y-10),
                              cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
                    
                    # Copy to validated directory
                    shutil.copy2(img_path, student_validated_dir / img_path.name)
                    logging.info(f"✓ Valid face detected in {img_path.name}")
                    
                elif len(faces) == 0:
                    # No face detected
                    cv2.putText(debug_img, 'No Face Detected', (10, 30),
                              cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
                    logging.warning(f"✗ No face detected in {img_path.name}")
                    
                else:
                    # Multiple faces detected
                    for (x, y, w, h) in faces:
                        cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 165, 255), 2)
                    cv2.putText(debug_img, f'Multiple Faces ({len(faces)})', (10, 30),
                              cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 165, 255), 2)
                    logging.warning(f"⚠ Multiple faces ({len(faces)}) detected in {img_path.name}")
                
                # Save debug image
                debug_path = student_validated_dir / f'debug_{img_path.name}'
                cv2.imwrite(str(debug_path), debug_img)
        
        # Print summary
        print("\n=== Face Validation Summary ===")
        print(f"Total images processed: {total_images}")
        print(f"Valid face images: {valid_images}")
        print(f"Invalid face images: {total_images - valid_images}")
        print(f"Validation rate: {(valid_images/total_images)*100:.1f}%")
        print(f"\nValid faces copied to: {validated_dir}")
        
    except Exception as e:
        logging.error(f"Error during face validation: {str(e)}")

if __name__ == '__main__':
    validate_faces() 