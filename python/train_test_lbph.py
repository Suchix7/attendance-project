import cv2
import numpy as np
import os
import sys
import json
import pickle
import logging
import math

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def preprocess_face(image):
    """Preprocess face image for better recognition"""
    # Convert to grayscale if needed
    if len(image.shape) > 2:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image
        
    # Apply histogram equalization
    gray = cv2.equalizeHist(gray)
    
    # Apply Gaussian blur to reduce noise
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    
    # Normalize the image
    gray = cv2.normalize(gray, None, 0, 255, cv2.NORM_MINMAX)
    
    return gray

def train_lbph():
    """Train LBPH model with existing student data"""
    try:
        # Get paths
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        students_dir = os.path.join(base_dir, 'students')
        models_dir = os.path.join(base_dir, 'models')
        cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
        model_path = os.path.join(models_dir, 'lbph_model.yml')
        labels_path = os.path.join(models_dir, 'labels.pkl')
        
        # Create models directory if it doesn't exist
        if not os.path.exists(models_dir):
            os.makedirs(models_dir)
            
        # Load face cascade
        face_cascade = cv2.CascadeClassifier(cascade_path)
        if face_cascade.empty():
            return {'success': False, 'message': 'Failed to load face cascade classifier'}
            
        # Initialize LBPH recognizer with optimized parameters
        recognizer = cv2.face.LBPHFaceRecognizer_create(
            radius=1,        # Reduced radius for more precise local features
            neighbors=8,     # Standard number of points
            grid_x=8,       # Standard grid
            grid_y=8,       # Standard grid
            threshold=100    # More balanced threshold
        )
        
        # Initialize training data
        face_images = []
        face_labels = []
        labels = {}
        next_label = 0
        
        # Process each student directory
        logging.info("Starting to process student directories...")
        for student_id in os.listdir(students_dir):
            student_dir = os.path.join(students_dir, student_id)
            if not os.path.isdir(student_dir):
                continue
                
            # Assign label for this student
            if student_id not in labels:
                labels[student_id] = next_label
                next_label += 1
                
            label = labels[student_id]
            logging.info(f"Processing student {student_id} with label {label}")
            
            # Process each face image
            for img_name in os.listdir(student_dir):
                if not img_name.startswith('face_') or not img_name.endswith('.jpg'):
                    continue
                    
                img_path = os.path.join(student_dir, img_name)
                logging.info(f"Processing image: {img_path}")
                
                # Read and preprocess image
                img = cv2.imread(img_path)
                if img is None:
                    logging.warning(f"Failed to load image: {img_path}")
                    continue
                
                # Convert to grayscale and preprocess
                gray = preprocess_face(img)
                
                # Detect face
                faces = face_cascade.detectMultiScale(
                    gray,
                    scaleFactor=1.1,
                    minNeighbors=5,
                    minSize=(50, 50),
                    maxSize=(300, 300)
                )
                
                if len(faces) == 0:
                    logging.warning(f"No face detected in {img_path}")
                    continue
                    
                # Process the largest face
                largest_face = max(faces, key=lambda rect: rect[2] * rect[3])
                x, y, w, h = largest_face
                
                # Extract and preprocess face region
                face_roi = gray[y:y+h, x:x+w]
                face_roi = cv2.resize(face_roi, (100, 100))
                
                # Add to training data
                face_images.append(face_roi)
                face_labels.append(label)
                
        if not face_images:
            return {'success': False, 'message': 'No valid face images found for training'}
            
        # Train model
        logging.info(f"Training model with {len(face_images)} faces...")
        recognizer.train(face_images, np.array(face_labels))
        
        # Save model and labels
        recognizer.save(model_path)
        with open(labels_path, 'wb') as f:
            pickle.dump(labels, f)
            
        logging.info("Training completed successfully")
        return {
            'success': True,
            'message': f'Model trained successfully with {len(face_images)} faces from {len(labels)} students',
            'num_faces': len(face_images),
            'num_students': len(labels)
        }
        
    except Exception as e:
        logging.error(f"Error during training: {str(e)}")
        return {'success': False, 'message': str(e)}

def test_recognition(test_image_path):
    """Test face recognition on a single image"""
    try:
        # Get paths
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        models_dir = os.path.join(base_dir, 'models')
        cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
        model_path = os.path.join(models_dir, 'lbph_model.yml')
        labels_path = os.path.join(models_dir, 'labels.pkl')
        
        # Check if model exists
        if not os.path.exists(model_path):
            return {'success': False, 'message': 'Face recognition model not found'}
            
        if not os.path.exists(labels_path):
            return {'success': False, 'message': 'Labels file not found'}
            
        # Load face cascade
        face_cascade = cv2.CascadeClassifier(cascade_path)
        if face_cascade.empty():
            return {'success': False, 'message': 'Failed to load face detection model'}
            
        # Load LBPH recognizer
        recognizer = cv2.face.LBPHFaceRecognizer_create()
        recognizer.read(model_path)
        
        # Load labels
        with open(labels_path, 'rb') as f:
            labels_dict = pickle.load(f)
            
        # Reverse the labels dictionary
        labels_reverse = {v: k for k, v in labels_dict.items()}
        
        # Read and preprocess image
        img = cv2.imread(test_image_path)
        if img is None:
            return {'success': False, 'message': 'Failed to load test image'}
            
        # Preprocess image
        gray = preprocess_face(img)
        
        # Detect faces with optimized parameters
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.2,    # More lenient scale factor
            minNeighbors=3,     # Reduced from 5 to 3
            minSize=(30, 30),   # Smaller minimum face size
            maxSize=(400, 400)  # Larger maximum face size
        )
        
        if len(faces) == 0:
            return {'success': False, 'message': 'No face detected in test image'}
            
        # Process the largest face
        largest_face = max(faces, key=lambda rect: rect[2] * rect[3])
        x, y, w, h = largest_face
        
        # Extract and preprocess face region
        face_roi = gray[y:y+h, x:x+w]
        face_roi = cv2.resize(face_roi, (100, 100))
        
        # Predict with distance threshold
        label, confidence = recognizer.predict(face_roi)
        
        # Get student ID from label
        predicted_student_id = labels_reverse.get(label, 'Unknown')
        
        # Calculate normalized confidence (0-100%)
        # Note: LBPH confidence is a distance measure, lower is better
        # Convert the LBPH distance to a percentage where 100 is perfect match
        raw_confidence = min(confidence, 200)  # Increased cap from 100 to 200
        confidence_percentage = 100 * math.exp(-0.02 * raw_confidence)  # Adjusted scaling factor
        
        # Only consider it a match if confidence is above 30%
        if confidence_percentage < 30:  # Lowered from 50% to 30%
            predicted_student_id = 'Unknown'
            
        # Save debug image
        debug_img = img.copy()
        color = (0, 255, 0) if predicted_student_id != 'Unknown' else (0, 0, 255)
        cv2.rectangle(debug_img, (x, y), (x+w, y+h), color, 2)
        text = f'{predicted_student_id} ({confidence_percentage:.1f}%)'
        cv2.putText(debug_img, text, (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        debug_path = os.path.join(os.path.dirname(test_image_path), 'debug_recognition.jpg')
        cv2.imwrite(debug_path, debug_img)
        
        return {
            'success': True,
            'predicted_student_id': predicted_student_id,
            'confidence': float(confidence_percentage),
            'face_location': {'x': int(x), 'y': int(y), 'width': int(w), 'height': int(h)},
            'message': f'Face recognized as Student {predicted_student_id} with {confidence_percentage:.1f}% confidence'
        }
        
    except Exception as e:
        logging.error(f"Error during recognition: {str(e)}")
        return {'success': False, 'message': str(e)}

if __name__ == '__main__':
    if len(sys.argv) < 2:
        # Train model if no arguments provided
        result = train_lbph()
    else:
        # Test recognition with provided image
        result = test_recognition(sys.argv[1])
        
    print(json.dumps(result, indent=2)) 