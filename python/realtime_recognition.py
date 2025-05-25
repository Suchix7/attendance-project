import cv2
import numpy as np
import os
import sys
import json
import pickle
from pathlib import Path
import logging
import argparse

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def train_lbph_with_validated_faces():
    """Train LBPH model using only the validated face images"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        validated_dir = base_dir / 'validated_faces'
        models_dir = base_dir / 'models'
        model_path = models_dir / 'lbph_model_validated.yml'
        labels_path = models_dir / 'labels_validated.pkl'
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        
        if not cascade_path.exists():
            return False, "Cascade file not found"
            
        # Create models directory if needed
        models_dir.mkdir(exist_ok=True)
        
        # Load face cascade
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        
        # Initialize LBPH recognizer
        recognizer = cv2.face.LBPHFaceRecognizer_create(
            radius=1,        # Local binary patterns radius
            neighbors=8,     # Number of points
            grid_x=8,       # Grid size
            grid_y=8,       # Grid size
            threshold=80    # Slightly higher threshold for better known face detection
        )
        
        # Initialize training data
        face_images = []
        face_labels = []
        labels = {}
        next_label = 0
        
        # Process each student directory
        for student_dir in validated_dir.iterdir():
            if not student_dir.is_dir() or student_dir.name == 'random':
                continue
                
            student_id = student_dir.name.replace('student', '')
            if student_id not in labels:
                labels[student_id] = next_label
                next_label += 1
            
            label = labels[student_id]
            logging.info(f"Processing student {student_id} with label {label}")
            
            # Process each face image
            face_count = 0
            for img_path in student_dir.glob('face_*.jpg'):
                if 'debug' in img_path.name:
                    continue
                
                logging.info(f"Processing image: {img_path}")
                
                # Read image
                img = cv2.imread(str(img_path))
                if img is None:
                    continue
                
                # Convert to grayscale
                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                
                # Detect face using the same cascade
                faces = face_cascade.detectMultiScale(
                    gray,
                    scaleFactor=1.1,
                    minNeighbors=4,
                    minSize=(30, 30),
                    maxSize=(400, 400)
                )
                
                if len(faces) == 1:
                    x, y, w, h = faces[0]
                    face_roi = gray[y:y+h, x:x+w]
                    
                    # Debug output
                    debug_img = img.copy()
                    cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 255, 0), 2)
                    debug_path = img_path.parent / f'training_debug_{img_path.name}'
                    cv2.imwrite(str(debug_path), debug_img)
                    
                    # Preprocess
                    face_roi = cv2.resize(face_roi, (100, 100))
                    face_roi = cv2.equalizeHist(face_roi)
                    
                    # Save preprocessed face for verification
                    prep_path = img_path.parent / f'prep_{img_path.name}'
                    cv2.imwrite(str(prep_path), face_roi)
                    
                    # Add original
                    face_images.append(face_roi)
                    face_labels.append(label)
                    
                    # Add flipped
                    flipped = cv2.flip(face_roi, 1)
                    face_images.append(flipped)
                    face_labels.append(label)
                    
                    # Add rotated versions
                    for angle in [-7, -3, 3, 7]:
                        M = cv2.getRotationMatrix2D((50, 50), angle, 1)
                        rotated = cv2.warpAffine(face_roi, M, (100, 100))
                        face_images.append(rotated)
                        face_labels.append(label)
                    
                    face_count += 1
                    logging.info(f"Successfully processed face from {img_path.name}")
                else:
                    logging.warning(f"Found {len(faces)} faces in {img_path.name} - skipping")
            
            logging.info(f"Processed {face_count} faces for student {student_id}")
        
        if not face_images:
            return False, "No valid face images found for training"
        
        # Train model
        logging.info(f"Training model with {len(face_images)} faces...")
        recognizer.train(face_images, np.array(face_labels))
        
        # Save model and labels
        recognizer.save(str(model_path))
        with open(labels_path, 'wb') as f:
            pickle.dump(labels, f)
        
        return True, f"Model trained with {len(face_images)} faces from {len(labels)} students"
        
    except Exception as e:
        logging.error(f"Error during training: {str(e)}")
        return False, str(e)

def start_recognition():
    """Start real-time face recognition using webcam"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        model_path = base_dir / 'models' / 'lbph_model_validated.yml'
        labels_path = base_dir / 'models' / 'labels_validated.pkl'
        
        # Check required files
        if not cascade_path.exists():
            print("Error: Face detection model not found")
            return
        if not model_path.exists() or not labels_path.exists():
            print("Error: Recognition model not found. Please train the model first.")
            return
        
        # Load face detection
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        
        # Load recognition model
        recognizer = cv2.face.LBPHFaceRecognizer_create()
        recognizer.read(str(model_path))
        
        # Load labels
        with open(labels_path, 'rb') as f:
            labels_dict = pickle.load(f)
        labels_reverse = {v: k for k, v in labels_dict.items()}
        
        # Start video capture
        cap = cv2.VideoCapture(0)
        if not cap.isOpened():
            print("Error: Could not open webcam")
            return
        
        print("Starting face recognition... Press 'q' to quit")
        
        while True:
            ret, frame = cap.read()
            if not ret:
                print("Error: Could not read frame")
                break
            
            # Create copy for drawing
            display = frame.copy()
            
            # Convert to grayscale
            gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
            
            # Apply histogram equalization
            gray = cv2.equalizeHist(gray)
            
            # Detect faces
            faces = face_cascade.detectMultiScale(
                gray,
                scaleFactor=1.1,
                minNeighbors=4,
                minSize=(30, 30),
                maxSize=(400, 400)
            )
            
            # Process each face
            for (x, y, w, h) in faces:
                # Extract and preprocess face region
                face_roi = gray[y:y+h, x:x+w]
                face_roi = cv2.resize(face_roi, (100, 100))
                face_roi = cv2.equalizeHist(face_roi)
                
                # Predict
                label, confidence = recognizer.predict(face_roi)
                
                # Adaptive confidence thresholds based on face size and position
                # Larger faces (closer to camera) can have slightly higher threshold
                face_size_factor = min(1.2, max(0.8, (w * h) / (200 * 200)))
                base_threshold = 75  # Base threshold for recognition
                adaptive_threshold = base_threshold * face_size_factor
                
                # The confidence is actually a distance - lower is better
                # Convert to a more intuitive percentage where higher is better
                confidence_percentage = max(0, min(100, 100 * (1 - confidence / 100)))
                
                # Recognition with adaptive threshold
                if confidence < adaptive_threshold:
                    name = f"Student {labels_reverse[label]}"
                    color = (0, 255, 0)  # Green
                    
                    # Add confidence level indicator with adjusted thresholds
                    if confidence < 50:
                        match_quality = "Excellent"
                        color = (0, 255, 0)  # Pure green for excellent
                    elif confidence < 65:
                        match_quality = "Good"
                        color = (0, 255, 100)  # Slightly different green for good
                    else:
                        match_quality = "Fair"
                        color = (0, 200, 100)  # More muted green for fair
                        
                else:
                    name = "Unknown"
                    match_quality = "No match"
                    confidence_percentage = 0
                    color = (0, 0, 255)  # Red
                
                # Draw rectangle and name
                cv2.rectangle(display, (x, y), (x+w, y+h), color, 2)
                
                # Show name and match quality
                text = f"{name} - {match_quality}"
                cv2.putText(display, text, (x, y-10),
                          cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
                
                # Show confidence values
                conf_text = f"Confidence: {confidence_percentage:.1f}%"
                cv2.putText(display, conf_text, (x, y+h+20),
                          cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
                
                raw_text = f"Distance: {confidence:.1f}"
                cv2.putText(display, raw_text, (x, y+h+40),
                          cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
            
            # Show frame
            cv2.imshow('Face Recognition', display)
            
            # Break loop on 'q' press
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
        
        # Clean up
        cap.release()
        cv2.destroyAllWindows()
        
    except Exception as e:
        logging.error(f"Error during recognition: {str(e)}")

def recognize_single_image(image_path):
    """Recognize a single image and return the result"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        model_path = base_dir / 'models' / 'lbph_model_validated.yml'
        labels_path = base_dir / 'models' / 'labels_validated.pkl'
        
        # Check required files
        if not all(p.exists() for p in [cascade_path, model_path, labels_path]):
            return {
                'success': False,
                'message': 'Required model files not found'
            }
        
        # Load face detection
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        
        # Load recognition model
        recognizer = cv2.face.LBPHFaceRecognizer_create()
        recognizer.read(str(model_path))
        
        # Load labels
        with open(labels_path, 'rb') as f:
            labels_dict = pickle.load(f)
        labels_reverse = {v: k for k, v in labels_dict.items()}
        
        # Read and process image
        img = cv2.imread(image_path)
        if img is None:
            return {
                'success': False,
                'message': 'Failed to load image'
            }
        
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Apply histogram equalization
        gray = cv2.equalizeHist(gray)
        
        # Detect faces
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=4,
            minSize=(30, 30),
            maxSize=(400, 400)
        )
        
        if len(faces) == 0:
            return {
                'success': False,
                'message': 'No face detected'
            }
        
        # Process the largest face if multiple faces are detected
        if len(faces) > 1:
            faces = [max(faces, key=lambda x: x[2] * x[3])]  # Select largest face by area
        
        # Get face location
        x, y, w, h = faces[0]
        face_location = {
            'x': int(x),
            'y': int(y),
            'width': int(w),
            'height': int(h)
        }
        
        # Extract and preprocess face region
        face_roi = gray[y:y+h, x:x+w]
        face_roi = cv2.resize(face_roi, (100, 100))
        face_roi = cv2.equalizeHist(face_roi)
        
        # Predict
        label, confidence = recognizer.predict(face_roi)
        
        # Convert confidence to percentage (0-100%)
        confidence_percentage = max(0, min(100, 100 * (1 - confidence / 100)))
        
        # Determine if face is recognized
        if confidence < 75:  # Using base threshold
            student_id = labels_reverse[label]
            return {
                'success': True,
                'student_id': student_id,  # Remove "Student " prefix
                'confidence': confidence_percentage,
                'face_location': face_location
            }
        else:
            return {
                'success': True,
                'student_id': "Unknown",
                'confidence': 0,
                'face_location': face_location
            }
            
    except Exception as e:
        logging.error(f"Recognition error: {str(e)}")
        return {
            'success': False,
            'message': str(e)
        }

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Face Recognition System')
    parser.add_argument('--train', action='store_true', help='Train the model')
    parser.add_argument('image_path', nargs='?', help='Path to image for recognition')
    args = parser.parse_args()

    if args.train:
        success, message = train_lbph_with_validated_faces()
        if not success:
            logging.error(f"Training failed: {message}")
            sys.exit(1)
        logging.info(f"Training successful: {message}")
        sys.exit(0)
    elif args.image_path:
        result = recognize_single_image(args.image_path)
        print(json.dumps(result))
    else:
        start_recognition() 