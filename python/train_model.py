import cv2
import numpy as np
import os
import sys
import json
import pickle
import logging

# Set up logging
logging.basicConfig(filename='face_recognition.log', level=logging.DEBUG)

def train_lbph_model(student_id):
    try:
        logging.info(f"Starting training for student {student_id}")
        
        # Get absolute paths
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        student_dir = os.path.join(base_dir, 'students', student_id)
        info_file = os.path.join(student_dir, 'info.json')
        models_dir = os.path.join(base_dir, 'models')
        assets_dir = os.path.join(base_dir, 'assets')
        
        logging.info(f"Using paths: base={base_dir}, student={student_dir}, info={info_file}")
        
        if not os.path.exists(info_file):
            logging.error(f"Student info not found at {info_file}")
            return {'success': False, 'message': f'Student info not found at {info_file}'}

        # Create models directory if it doesn't exist
        os.makedirs(models_dir, exist_ok=True)
        
        # Initialize or load existing model
        model_path = os.path.join(models_dir, 'lbph_model.yml')
        labels_path = os.path.join(models_dir, 'labels.pkl')
        cascade_path = os.path.join(assets_dir, 'haarcascade_frontalface_default.xml')
        
        if not os.path.exists(cascade_path):
            logging.error(f"Haar cascade classifier not found at {cascade_path}")
            return {'success': False, 'message': 'Face detection model not found'}
        
        # Load existing labels if they exist
        if os.path.exists(labels_path):
            logging.info("Loading existing labels")
            with open(labels_path, 'rb') as f:
                labels = pickle.load(f)
        else:
            logging.info("Creating new labels dictionary")
            labels = {}

        # Get next available label
        next_label = max(labels.values()) + 1 if labels else 0
        logging.info(f"Using label {next_label} for student {student_id}")

        # Initialize lists for all training data
        all_face_images = []
        all_face_labels = []

        # First, load existing training data if model exists
        if os.path.exists(model_path):
            logging.info("Loading existing model and training data")
            recognizer = cv2.face.LBPHFaceRecognizer_create(
                radius=1,
                neighbors=8,
                grid_x=8,
                grid_y=8,
                threshold=50
            )
            recognizer.read(model_path)
            
            # Load all existing student data
            for existing_id, label in labels.items():
                existing_dir = os.path.join(base_dir, 'students', existing_id)
                if os.path.exists(existing_dir):
                    for i in range(1, 11):  # 10 face images per student
                        face_path = os.path.join(existing_dir, f'face_{i}.jpg')
                        if os.path.exists(face_path):
                            img = cv2.imread(face_path)
                            if img is not None:
                                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                                gray = cv2.equalizeHist(gray)
                                face_cascade = cv2.CascadeClassifier(cascade_path)
                                faces = face_cascade.detectMultiScale(gray, scaleFactor=1.05, minNeighbors=3)
                                if len(faces) > 0:
                                    x, y, w, h = faces[0]
                                    face_roi = gray[y:y+h, x:x+w]
                                    face_roi = cv2.resize(face_roi, (100, 100))
                                    face_roi = cv2.GaussianBlur(face_roi, (5, 5), 0)
                                    all_face_images.append(face_roi)
                                    all_face_labels.append(label)
        else:
            logging.info("Creating new model")
            recognizer = cv2.face.LBPHFaceRecognizer_create(
                radius=1,
                neighbors=8,
                grid_x=8,
                grid_y=8,
                threshold=50
            )

        # Process new student's face images
        face_count = 0
        for i in range(1, 11):  # 10 face images per student
            face_path = os.path.join(student_dir, f'face_{i}.jpg')
            if os.path.exists(face_path):
                logging.info(f"Processing face image {i}")
                # Read and preprocess image
                img = cv2.imread(face_path)
                if img is None:
                    logging.error(f"Failed to load image {face_path}")
                    continue
                    
                gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
                gray = cv2.equalizeHist(gray)
                
                # Detect face
                face_cascade = cv2.CascadeClassifier(cascade_path)
                if face_cascade.empty():
                    logging.error("Failed to load face cascade classifier")
                    return {'success': False, 'message': 'Face detection model not found'}

                faces = face_cascade.detectMultiScale(
                    gray,
                    scaleFactor=1.05,
                    minNeighbors=3,
                    minSize=(30, 30),
                    maxSize=(300, 300)
                )
                
                if len(faces) > 0:
                    x, y, w, h = faces[0]
                    face_roi = gray[y:y+h, x:x+w]
                    face_roi = cv2.resize(face_roi, (100, 100))
                    face_roi = cv2.GaussianBlur(face_roi, (5, 5), 0)
                    all_face_images.append(face_roi)
                    all_face_labels.append(next_label)
                    face_count += 1
                    logging.info(f"Successfully processed face {i}")
                else:
                    logging.warning(f"No face detected in image {i}")

        if not all_face_images:
            logging.error("No valid face images found")
            return {'success': False, 'message': 'No valid face images found'}

        logging.info(f"Processed {face_count} valid face images for new student")

        # Update labels
        labels[student_id] = next_label
        with open(labels_path, 'wb') as f:
            pickle.dump(labels, f)
        logging.info("Updated labels saved")

        # Train model with all data
        try:
            logging.info("Starting model training with all data")
            recognizer.train(all_face_images, np.array(all_face_labels))
            recognizer.save(model_path)
            logging.info("Model training completed successfully")
            return {'success': True, 'message': 'Model trained successfully'}
        except Exception as e:
            logging.error(f"Error during model training: {str(e)}")
            return {'success': False, 'message': str(e)}

    except Exception as e:
        logging.error(f"Unexpected error: {str(e)}")
        return {'success': False, 'message': str(e)}

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print(json.dumps({'success': False, 'message': 'Invalid arguments'}))
        sys.exit(1)

    student_id = sys.argv[1]
    result = train_lbph_model(student_id)
    print(json.dumps(result)) 