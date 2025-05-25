import cv2
import numpy as np
import os
import sys
import json
import pickle
import logging
from pathlib import Path

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def preprocess_face(image, target_size=(100, 100)):
    """Preprocess the already detected face for recognition"""
    if len(image.shape) > 2:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image.copy()
    
    # Apply CLAHE for better contrast
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
    gray = clahe.apply(gray)
    
    # Resize to standard size
    gray = cv2.resize(gray, target_size)
    
    # Normalize the image
    gray = cv2.normalize(gray, None, 0, 255, cv2.NORM_MINMAX)
    
    return gray

def train_lbph_model():
    """Train LBPH model using validated face images"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        validated_dir = base_dir / 'validated_faces'  # Use validated faces directory
        models_dir = base_dir / 'models'
        model_path = models_dir / 'lbph_model.yml'
        labels_path = models_dir / 'labels.pkl'
        
        # Check if validated faces directory exists
        if not validated_dir.exists():
            return {'success': False, 'message': 'Validated faces directory not found. Please run validate_faces.py first.'}
        
        # Create models directory if needed
        models_dir.mkdir(exist_ok=True)
        
        # Initialize LBPH recognizer with optimized parameters
        recognizer = cv2.face.LBPHFaceRecognizer_create(
            radius=2,        # Increased radius for more distinctive features
            neighbors=12,    # More sampling points
            grid_x=10,      # More cells for better spatial information
            grid_y=10,      # More cells for better spatial information
            threshold=100    # Threshold for recognition
        )
        
        # Initialize training data
        face_images = []
        face_labels = []
        labels = {}
        next_label = 0
        
        # Process each student directory
        logging.info("Starting to process validated face images...")
        
        for student_dir in validated_dir.iterdir():
            if not student_dir.is_dir():
                continue
            
            student_id = student_dir.name
            if student_id not in labels:
                labels[student_id] = next_label
                next_label += 1
            
            label = labels[student_id]
            logging.info(f"Processing student {student_id} with label {label}")
            
            # Process each face image (these are validated faces)
            face_count = 0
            for img_path in student_dir.glob('face_*.jpg'):
                # Skip debug images
                if img_path.name.startswith('debug_'):
                    continue
                    
                logging.info(f"Processing image: {img_path}")
                
                # Read image
                img = cv2.imread(str(img_path))
                if img is None:
                    logging.warning(f"Failed to load image: {img_path}")
                    continue
                
                # Preprocess face
                processed_face = preprocess_face(img)
                
                # Add to training data
                face_images.append(processed_face)
                face_labels.append(label)
                
                # Add slightly rotated versions for better training
                for angle in [-5, 5]:
                    rows, cols = processed_face.shape
                    M = cv2.getRotationMatrix2D((cols/2, rows/2), angle, 1)
                    rotated = cv2.warpAffine(processed_face, M, (cols, rows))
                    face_images.append(rotated)
                    face_labels.append(label)
                
                face_count += 1
            
            logging.info(f"Processed {face_count} faces for student {student_id}")
        
        if not face_images:
            return {'success': False, 'message': 'No valid face images found for training'}
        
        # Train model
        logging.info(f"Training model with {len(face_images)} faces...")
        recognizer.train(face_images, np.array(face_labels))
        
        # Save model and labels
        recognizer.save(str(model_path))
        with open(labels_path, 'wb') as f:
            pickle.dump(labels, f)
        
        return {
            'success': True,
            'message': f'Model trained successfully with {len(face_images)} faces from {len(labels)} students',
            'num_faces': len(face_images),
            'num_students': len(labels)
        }
        
    except Exception as e:
        logging.error(f"Error during training: {str(e)}")
        return {'success': False, 'message': str(e)}

def recognize_face(face_image_path):
    """Recognize an already detected face"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        models_dir = base_dir / 'models'
        model_path = models_dir / 'lbph_model.yml'
        labels_path = models_dir / 'labels.pkl'
        
        # Check files exist
        if not model_path.exists() or not labels_path.exists():
            return {'success': False, 'message': 'Model files not found'}
        
        # Load LBPH recognizer
        recognizer = cv2.face.LBPHFaceRecognizer_create()
        recognizer.read(str(model_path))
        
        # Load labels
        with open(labels_path, 'rb') as f:
            labels_dict = pickle.load(f)
        labels_reverse = {v: k for k, v in labels_dict.items()}
        
        # Read and process image
        img = cv2.imread(str(face_image_path))
        if img is None:
            return {'success': False, 'message': 'Failed to load image'}
        
        # Preprocess face
        processed_face = preprocess_face(img)
        
        # Try recognition with original and slightly rotated versions
        predictions = []
        angles = [0, -5, 5]  # Test multiple angles
        
        for angle in angles:
            if angle == 0:
                test_face = processed_face
            else:
                rows, cols = processed_face.shape
                M = cv2.getRotationMatrix2D((cols/2, rows/2), angle, 1)
                test_face = cv2.warpAffine(processed_face, M, (cols, rows))
            
            label, confidence = recognizer.predict(test_face)
            predictions.append((label, confidence))
        
        # Get the best prediction (lowest confidence value)
        best_label, best_confidence = min(predictions, key=lambda x: x[1])
        
        # Convert LBPH distance to confidence percentage (0-100%)
        max_confidence = 100  # Maximum confidence threshold
        confidence_percentage = max(0, min(100, 100 * (1 - best_confidence / max_confidence)))
        
        # Determine if the face is recognized
        threshold = 35  # Recognition threshold percentage
        predicted_student_id = labels_reverse.get(best_label, 'Unknown')
        if confidence_percentage < threshold:
            predicted_student_id = 'Unknown'
            confidence_percentage = 100 - confidence_percentage  # Invert for unknown faces
        
        # Save debug image with recognition result
        debug_img = img.copy()
        color = (0, 255, 0) if predicted_student_id != 'Unknown' else (0, 0, 255)
        h, w = img.shape[:2]
        cv2.putText(debug_img, f'{predicted_student_id} ({confidence_percentage:.1f}%)', 
                   (10, h-10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        debug_path = Path(face_image_path).parent / f'debug_recognition_{Path(face_image_path).name}'
        cv2.imwrite(str(debug_path), debug_img)
        
        return {
            'success': True,
            'predicted_student_id': predicted_student_id,
            'confidence': confidence_percentage,
            'message': f'Face recognized as {predicted_student_id} with {confidence_percentage:.1f}% confidence'
        }
        
    except Exception as e:
        logging.error(f"Error during recognition: {str(e)}")
        return {'success': False, 'message': str(e)}

if __name__ == '__main__':
    if len(sys.argv) < 2:
        # Train model if no arguments provided
        result = train_lbph_model()
    else:
        # Test recognition with provided image
        result = recognize_face(sys.argv[1])
    
    print(json.dumps(result, indent=2)) 