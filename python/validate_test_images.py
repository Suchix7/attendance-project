import cv2
import os
import shutil
from pathlib import Path
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def validate_test_images():
    """Validate test images and organize them into categories"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        test_dir = base_dir / 'test_images'
        tested_dir = base_dir / 'tested_faces'
        
        if not test_dir.exists():
            logging.error(f"Test images directory not found at: {test_dir}")
            return
        
        # Create tested faces directory structure
        tested_dir.mkdir(exist_ok=True)
        student45_dir = tested_dir / 'student45'
        student99_dir = tested_dir / 'student99'
        random_dir = tested_dir / 'random'
        
        student45_dir.mkdir(exist_ok=True)
        student99_dir.mkdir(exist_ok=True)
        random_dir.mkdir(exist_ok=True)
        
        # Load face cascade
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        if not cascade_path.exists():
            logging.error(f"Cascade file not found at: {cascade_path}")
            return
            
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        if face_cascade.empty():
            logging.error("Failed to load face cascade classifier")
            return
        
        # Initialize counters
        stats = {
            'student45': {'total': 0, 'valid': 0},
            'student99': {'total': 0, 'valid': 0},
            'random': {'total': 0, 'valid': 0}
        }
        
        # Process test images
        for img_path in test_dir.glob('*.jpg'):
            # Determine image category
            if img_path.name.startswith('test45_'):
                category = 'student45'
                target_dir = student45_dir
            elif img_path.name.startswith('test99_'):
                category = 'student99'
                target_dir = student99_dir
            elif img_path.name.startswith('random'):
                category = 'random'
                target_dir = random_dir
            else:
                continue
            
            stats[category]['total'] += 1
            logging.info(f"\nProcessing {category} image: {img_path.name}")
            
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
                stats[category]['valid'] += 1
                x, y, w, h = faces[0]
                
                # Draw green rectangle for valid face
                cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 255, 0), 2)
                cv2.putText(debug_img, 'Valid Face', (x, y-10),
                          cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
                
                # Extract and save just the face region
                face_img = img[y:y+h, x:x+w]
                face_path = target_dir / f'face_{img_path.name}'
                cv2.imwrite(str(face_path), face_img)
                
                # Also save the original image
                shutil.copy2(img_path, target_dir / img_path.name)
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
            debug_path = target_dir / f'debug_{img_path.name}'
            cv2.imwrite(str(debug_path), debug_img)
        
        # Print summary
        print("\n=== Test Images Validation Summary ===")
        
        for category in ['student45', 'student99', 'random']:
            total = stats[category]['total']
            valid = stats[category]['valid']
            if total > 0:
                print(f"\n{category}:")
                print(f"- Total images: {total}")
                print(f"- Valid faces: {valid}")
                print(f"- Invalid faces: {total - valid}")
                print(f"- Validation rate: {(valid/total)*100:.1f}%")
        
        total_images = sum(stats[c]['total'] for c in stats)
        total_valid = sum(stats[c]['valid'] for c in stats)
        
        print(f"\nOverall:")
        print(f"- Total images processed: {total_images}")
        print(f"- Total valid faces: {total_valid}")
        print(f"- Overall validation rate: {(total_valid/total_images)*100:.1f}%")
        print(f"\nProcessed images saved to: {tested_dir}")
        
    except Exception as e:
        logging.error(f"Error during test images validation: {str(e)}")

if __name__ == '__main__':
    validate_test_images() 