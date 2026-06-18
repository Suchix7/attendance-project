import cv2
import numpy as np
import os
import sys
import json
import pickle
from pathlib import Path
import logging
import argparse
import gc

# Configure logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

def train_models():
    """Train LBPH, Eigenfaces, and Fisherfaces models using only the validated face images"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        validated_dir = base_dir / 'students'
        models_dir = base_dir / 'models'
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        
        # Model paths
        lbph_path = models_dir / 'lbph_model.yml'
        eigen_path = models_dir / 'eigen_model.yml'
        fisher_path = models_dir / 'fisher_model.yml'
        labels_path = models_dir / 'labels.pkl'
        
        if not cascade_path.exists():
            return False, "Cascade file not found"
            
        models_dir.mkdir(exist_ok=True)
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        
        # Initialize recognizers
        recognizer_lbph = cv2.face.LBPHFaceRecognizer_create(radius=1, neighbors=8, grid_x=8, grid_y=8, threshold=150)
        recognizer_eigen = cv2.face.EigenFaceRecognizer_create()
        recognizer_fisher = cv2.face.FisherFaceRecognizer_create()
        
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
        
        face_images = []
        face_labels = []
        labels = {}
        next_label = 0
        
        # Process each student directory
        for student_dir in validated_dir.iterdir():
            if not student_dir.is_dir() or student_dir.name in ['random', 'temp']:
                continue
                
            student_id = student_dir.name
            if student_id not in labels:
                labels[student_id] = next_label
                next_label += 1
            
            label = labels[student_id]
            logging.info(f"Processing student {student_id} with label {label}")
            
            for img_path in student_dir.glob('face_*.jpg'):
                if 'debug' in img_path.name: continue
                img = cv2.imread(str(img_path))
                if img is None: continue
                
                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                faces = face_cascade.detectMultiScale(gray, 1.1, 4, minSize=(30, 30))
                
                if len(faces) >= 1:
                    x, y, w, h = max(faces, key=lambda f: f[2] * f[3])
                    face_roi = gray[y:y+h, x:x+w]
                    face_roi = cv2.resize(face_roi, (96, 96))
                    face_roi = clahe.apply(face_roi)
                    face_roi = cv2.GaussianBlur(face_roi, (3, 3), 0)
                    
                    # Augmentation
                    face_images.append(face_roi)
                    face_labels.append(label)
                    face_images.append(cv2.flip(face_roi, 1))
                    face_labels.append(label)
                    
                    for angle in [-5, 5]:
                        M = cv2.getRotationMatrix2D((48, 48), angle, 1)
                        face_images.append(cv2.warpAffine(face_roi, M, (96, 96)))
                        face_labels.append(label)
        
        if not face_images:
            return False, "No valid face images found for training"
        
        gc.collect()
        
        # Train LBPH
        logging.info("Training LBPH...")
        recognizer_lbph.train(face_images, np.array(face_labels))
        recognizer_lbph.save(str(lbph_path))
        
        # Train Eigenfaces
        logging.info("Training Eigenfaces...")
        recognizer_eigen.train(face_images, np.array(face_labels))
        recognizer_eigen.save(str(eigen_path))
        
        # Train Fisherfaces (requires at least 2 distinct labels)
        if len(labels) >= 2:
            logging.info("Training Fisherfaces...")
            recognizer_fisher.train(face_images, np.array(face_labels))
            recognizer_fisher.save(str(fisher_path))
        else:
            logging.warn("Fisherfaces skipped: need at least 2 students")
            
        with open(str(labels_path), 'wb') as f:
            pickle.dump(labels, f)
        
        return True, f"Trained {len(labels)} students with {len(face_images)} images using multiple algorithms"
        
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
                # Extract and preprocess: Focus on texture consistency
                face_roi = gray[y:y+h, x:x+w]
                face_roi = cv2.resize(face_roi, (96, 96))
                clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
                face_roi = clahe.apply(face_roi)
                
                # Predict
                label, confidence = recognizer.predict(face_roi)
                
                # Adaptive confidence thresholds based on face size and position
                # Larger faces (closer to camera) can have slightly higher threshold
                face_size_factor = min(1.2, max(0.8, (w * h) / (200 * 200)))
                base_threshold = 75  # Base threshold for recognition
                # The confidence is actually a distance - lower is better
                # Using 150 as a normalization factor for 96x96 LBP
                confidence_percentage = max(0, min(100, 100 * (1 - confidence / 150)))
                
                # Recognition with adaptive threshold - relaxed for stability
                if confidence < 105:
                    name = f"Student {labels_reverse[label]}"
                    color = (0, 255, 0)  # Green
                    
                    # Add confidence level indicator with adjusted thresholds
                    if confidence < 60:
                        match_quality = "Excellent"
                        color = (0, 255, 0)
                    elif confidence < 85:
                        match_quality = "Good"
                        color = (0, 255, 100)
                    else:
                        match_quality = "Fair"
                        color = (0, 200, 100)
                        
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

def recognize_single_image(image_path, algo='lbph'):
    """Recognize a single image using the specified algorithm and return the result"""
    try:
        # Get paths
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        cascade_path = base_dir / 'assets' / 'haarcascade_frontalface_default.xml'
        model_file = f'{algo}_model.yml'
        model_path = base_dir / 'models' / model_file
        labels_path = base_dir / 'models' / 'labels.pkl'
        
        # Check required files
        if not all(p.exists() for p in [cascade_path, model_path, labels_path]):
            return {
                'success': False,
                'message': 'Required model files not found'
            }
        
        # Load face detection
        face_cascade = cv2.CascadeClassifier(str(cascade_path))
        
        # Load recognition model based on algorithm
        if algo == 'lbph':
            recognizer = cv2.face.LBPHFaceRecognizer_create()
        elif algo == 'eigen':
            recognizer = cv2.face.EigenFaceRecognizer_create()
        elif algo == 'fisher':
            recognizer = cv2.face.FisherFaceRecognizer_create()
        else:
            return {'success': False, 'message': f'Unknown algorithm: {algo}'}

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
        
        # Detect faces with robust parameters on raw gray to align with training
        faces = face_cascade.detectMultiScale(
            gray,
            scaleFactor=1.1,
            minNeighbors=5,
            minSize=(40, 40),
            maxSize=(600, 600)
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
        
        # Extract and preprocess: Consistent texture preservation (crop from raw gray)
        # We want to crop a 96x96 face ROI.
        # To generate translations, we crop various offsets of the face ROI
        H, W = gray.shape
        
        # Base crop
        face_roi = gray[y:y+h, x:x+w]
        face_roi_resized = cv2.resize(face_roi, (96, 96))
        
        # Multi-sample generation for alignment robustness
        samples = [
            face_roi_resized,
            cv2.flip(face_roi_resized, 1)  # Horizontal flip for mirrored cameras
        ]
        
        # Shift translation samples
        shifts = [(-4, -4), (-4, 4), (4, -4), (4, 4), (-2, 0), (2, 0), (0, -2), (0, 2)]
        for dx, dy in shifts:
            nx, ny = x + dx, y + dy
            roi = gray[max(0, ny):min(H, ny+h), max(0, nx):min(W, nx+w)]
            if roi.size > 0:
                samples.append(cv2.resize(roi, (96, 96)))
                
        # Scale samples
        scale_changes = [-0.05, 0.05]
        for ds in scale_changes:
            dw = int(w * ds)
            dh = int(h * ds)
            roi = gray[max(0, y-dh):min(H, y+h+dh), max(0, x-dw):min(W, x+w+dw)]
            if roi.size > 0:
                samples.append(cv2.resize(roi, (96, 96)))
                
        # Rotation samples
        for angle in [-5, 5]:
            M = cv2.getRotationMatrix2D((48, 48), angle, 1)
            samples.append(cv2.warpAffine(face_roi_resized, M, (96, 96)))
            
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8,8))
        best_confidence = float('inf')
        best_label = -1
        
        for sample in samples:
            sample_prep = clahe.apply(sample)
            
            # Quality check on the base sample only
            if np.array_equal(sample, face_roi_resized):
                laplacian_var = cv2.Laplacian(sample_prep, cv2.CV_64F).var()
                if laplacian_var < 10: # Reject very blurry images
                    return {
                        'success': False,
                        'message': 'Image too blurry. Please look steadily at the camera.'
                    }
            
            sample_prep = cv2.GaussianBlur(sample_prep, (3, 3), 0)
            label, confidence = recognizer.predict(sample_prep)
            if confidence < best_confidence:
                best_confidence = confidence
                best_label = label
                
        label, confidence = best_label, best_confidence
        
        # Define strict thresholds to avoid mismatches
        # LBPH: 80 (stricter, lower distance is closer match)
        # Eigen: 3500
        # Fisher: 400
        if algo == 'lbph':
            thresh = 90
            if confidence < thresh:
                # Map raw distance [40 to 90] to [100% to 60%] display confidence
                confidence_percentage = max(60.0, min(100.0, 100.0 - (confidence - 40.0) * (40.0 / 50.0)))
            else:
                confidence_percentage = 0.0
        elif algo == 'eigen':
            thresh = 3500
            if confidence < thresh:
                # Map raw distance [1000 to 3500] to [100% to 60%]
                confidence_percentage = max(60.0, min(100.0, 100.0 - (confidence - 1000.0) * (40.0 / 2500.0)))
            else:
                confidence_percentage = 0.0
        else: # fisher
            thresh = 400
            if confidence < thresh:
                # Map raw distance [50 to 400] to [100% to 60%]
                confidence_percentage = max(60.0, min(100.0, 100.0 - (confidence - 50.0) * (40.0 / 350.0)))
            else:
                confidence_percentage = 0.0
        
        if confidence < thresh:  
            student_id = labels_reverse[label]
            return {
                'success': True,
                'student_id': student_id,
                'confidence': confidence_percentage,
                'algorithm': algo,
                'raw_distance': round(confidence, 2),
                'face_location': face_location
            }
        else:
            return {
                'success': True,
                'student_id': "Unknown",
                'confidence': 0,
                'algorithm': algo,
                'raw_distance': round(confidence, 2),
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
    parser.add_argument('--train', action='store_true', help='Train the models')
    parser.add_argument('--algorithm', default='lbph', choices=['lbph', 'eigen', 'fisher'], help='Algorithm to use')
    parser.add_argument('image_path', nargs='?', help='Path to image for recognition')
    args = parser.parse_args()

    if args.train:
        success, message = train_models()
        result = {'success': success, 'message': message}
        print(json.dumps(result))
        if not success:
            sys.exit(1)
        sys.exit(0)
    elif args.image_path:
        result = recognize_single_image(args.image_path, args.algorithm)
        print(json.dumps(result))
    else:
        start_recognition() 