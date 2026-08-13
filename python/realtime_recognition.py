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

import spoof_check  # hardcoded anti-spoofing (passive screen check + active blink liveness)

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
            # Clean up existing model files if they exist to avoid stale recognitions
            for model_file_path in [lbph_path, eigen_path, fisher_path, labels_path]:
                if model_file_path.exists():
                    try:
                        model_file_path.unlink()
                        logging.info(f"Deleted stale model file: {model_file_path}")
                    except Exception as ex:
                        logging.error(f"Failed to delete {model_file_path}: {str(ex)}")
            return True, "No valid face images found. Models successfully reset."
        
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
            logging.warning("Fisherfaces skipped: need at least 2 students")
            if fisher_path.exists():
                try:
                    fisher_path.unlink()
                    logging.info(f"Deleted stale Fisherfaces model file: {fisher_path}")
                except Exception as ex:
                    logging.error(f"Failed to delete {fisher_path}: {str(ex)}")
            
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

# NOTE: the passive anti-spoof checks (detect_spoof / liveness_report) now live
# in python/spoof_check.py and are used via the `spoof_check` module below.


def recognize_single_image(image_path, algo='lbph', check_liveness=True):
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
            # Fallback for dim / uneven lighting: the raw pass misses faces in low
            # light. Retry on a histogram-equalized copy with slightly relaxed
            # neighbours. This is DETECTION ONLY — the face box found here is still
            # cropped from the raw `gray` below, so recognition preprocessing stays
            # aligned with how the model was trained.
            eq = cv2.equalizeHist(gray)
            faces = face_cascade.detectMultiScale(
                eq,
                scaleFactor=1.1,
                minNeighbors=4,
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

        # --- Passive liveness / anti-spoof gate (recognition path only) ---
        # Reject faces coming from a phone/monitor screen or a printed photo.
        # Skipped when check_liveness is False (e.g. the registration
        # duplicate-check, which uses live-captured images already).
        if check_liveness and spoof_check.ANTISPOOF_ENABLED:
            is_spoof, spoof_reason, live_metrics = spoof_check.detect_spoof(
                gray[y:y + h, x:x + w], img[y:y + h, x:x + w])
            logging.info(f"LIVENESS {os.path.basename(str(image_path))}: {live_metrics} "
                         f"spoof={is_spoof} ({spoof_reason})")
            if is_spoof:
                return {
                    'success': False,
                    'message': 'Spoof detected: please use your real face, not a photo or phone screen',
                    'liveness': 'fake',
                    'spoof_reason': spoof_reason,
                    'face_location': face_location
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
            # Eigenfaces distance for a trained student from webcam is typically 1500–3300.
            # Unknown faces score much higher (5000–15000+) when more students are enrolled.
            # With only 1 student trained the gap is smaller, but 3500 correctly identifies
            # the enrolled student while rejecting truly distant faces.
            thresh = 3500
            if confidence < thresh:
                # Map raw distance [500 to 3500] to [100% to 60%]
                confidence_percentage = max(60.0, min(100.0, 100.0 - (confidence - 500.0) * (40.0 / 3000.0)))
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

# -- Consensus recognition ----------------------------------------------------
# A single algorithm (LBPH) will occasionally match a stranger — e.g. a face on
# a phone screen — to an enrolled student. Requiring agreement across the three
# independently-trained models removes almost all of those false accepts, since
# a wrong match rarely fools more than one algorithm.
CONSENSUS_MIN_AGREE = 2   # how many of the 3 algorithms must agree on the same id


def recognize_consensus_image(image_path):
    """Recognise a face only if at least CONSENSUS_MIN_AGREE of the available
    algorithms (lbph/eigen/fisher) agree on the same student. The single-frame
    phone-screen (anti-spoof) gate is applied once, via the LBPH pass."""
    try:
        # LBPH pass carries the passive phone-screen / liveness gate.
        base = recognize_single_image(image_path, 'lbph', check_liveness=True)
        if not base.get('success'):
            # No face, blurry, or flagged as a phone/photo screen -> not recognised.
            return base

        votes = {}
        for algo in ('lbph', 'eigen', 'fisher'):
            r = base if algo == 'lbph' else recognize_single_image(image_path, algo, check_liveness=False)
            if r.get('success') and r.get('student_id', 'Unknown') != 'Unknown':
                votes[r['student_id']] = votes.get(r['student_id'], 0) + 1

        face_location = base.get('face_location')
        if votes:
            best_id = max(votes, key=lambda s: votes[s])
            if votes[best_id] >= CONSENSUS_MIN_AGREE:
                return {
                    'success': True,
                    'student_id': best_id,
                    'confidence': base.get('confidence', 0) if base.get('student_id') == best_id else 70,
                    'algorithm': 'consensus',
                    'agree': votes[best_id],
                    'face_location': face_location
                }

        logging.info(f"CONSENSUS rejected {os.path.basename(str(image_path))}: votes={votes}")
        return {
            'success': True,
            'student_id': 'Unknown',
            'confidence': 0,
            'algorithm': 'consensus',
            'agree': max(votes.values()) if votes else 0,
            'face_location': face_location
        }
    except Exception as e:
        logging.error(f"Consensus recognition error: {str(e)}")
        return {'success': False, 'message': str(e)}


# -- Duplicate-face detection --------------------------------------------------
# Minimum number of freshly-captured images that must be recognised as the SAME
# already-registered student before we treat the new sign-up as a duplicate.
DUP_MIN_VOTES = 3


def check_duplicate_face(images_dir, algo='lbph'):
    """Compare a folder of freshly-captured faces against the EXISTING trained
    model to decide whether this person is already registered.

    Reuses recognize_single_image() so the exact same preprocessing and
    per-algorithm distance thresholds are applied. Each captured image that is
    recognised as an existing student casts one vote for that student. If a
    single student collects at least DUP_MIN_VOTES votes, the registration is a
    duplicate.

    Returns a dict: {success, is_duplicate, matched_student_id, votes,
    total_checked, ...}. When no model exists yet (first ever student),
    is_duplicate is False so the first registration is always allowed.
    """
    try:
        base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        model_path = base_dir / 'models' / f'{algo}_model.yml'
        labels_path = base_dir / 'models' / 'labels.pkl'

        images_dir = Path(images_dir)
        if not images_dir.is_dir():
            return {'success': False, 'message': f'Images directory not found: {images_dir}'}

        # No model yet -> nobody registered -> allow this registration.
        if not model_path.exists() or not labels_path.exists():
            return {'success': True, 'is_duplicate': False, 'reason': 'no_model', 'total_checked': 0}

        votes = {}          # student_id -> matching-image count
        confidences = {}    # student_id -> list of display confidences
        total_checked = 0   # images where a face was detected & predicted

        for img_path in sorted(images_dir.glob('face_*.jpg')):
            if 'debug' in img_path.name:
                continue

            res = recognize_single_image(str(img_path), algo, check_liveness=False)
            if not res.get('success'):
                continue  # e.g. no face / blurry — doesn't count for or against

            total_checked += 1
            sid = res.get('student_id', 'Unknown')
            logging.info(f"{img_path.name}: -> {sid} (raw_distance={res.get('raw_distance')})")

            if sid != 'Unknown':
                votes[sid] = votes.get(sid, 0) + 1
                confidences.setdefault(sid, []).append(res.get('confidence', 0))

        if not votes:
            return {'success': True, 'is_duplicate': False, 'total_checked': total_checked}

        best_id = max(votes.keys(), key=lambda s: votes[s])
        best_votes = votes[best_id]
        avg_conf = round(sum(confidences[best_id]) / len(confidences[best_id]), 1)

        return {
            'success': True,
            'is_duplicate': best_votes >= DUP_MIN_VOTES,
            'matched_student_id': best_id,
            'votes': best_votes,
            'total_checked': total_checked,
            'avg_confidence': avg_conf,
            'algorithm': algo
        }

    except Exception as e:
        logging.error(f"Duplicate check error: {str(e)}")
        return {'success': False, 'message': str(e)}


# -- Active liveness: blink challenge -----------------------------------------
# The blink detection itself lives in spoof_check.detect_blink(); here we wrap
# it so that, once a person is confirmed live, they are identified with the
# 2-of-3 consensus recogniser.
def verify_liveness(frames_dir, algo='lbph'):
    """Confirm a real blink across a burst of frames, then recognise the person
    with consensus. Returns {success, live, blinks, student_id, confidence, ...}."""
    blink = spoof_check.detect_blink(frames_dir)
    if not blink.get('success'):
        return blink

    result = {
        'success': True,
        'live': blink.get('live', False),
        'blinks': blink.get('blinks', 0),
        'frames_total': blink.get('frames_total', 0),
        'frames_face': blink.get('frames_face', 0),
        'frames_open': blink.get('frames_open', 0),
        'frames_closed': blink.get('frames_closed', 0),
        'student_id': 'Unknown',
        'confidence': 0
    }

    # Identify the person on the clearest eyes-open frame using consensus.
    if result['live'] and blink.get('best_open_path'):
        rec = recognize_consensus_image(blink['best_open_path'])
        if rec.get('success'):
            result['student_id'] = rec.get('student_id', 'Unknown')
            result['confidence'] = rec.get('confidence', 0)
            result['raw_distance'] = rec.get('raw_distance')

    logging.info(f"LIVENESS-VERIFY live={result['live']} blinks={result['blinks']} "
                 f"student={result['student_id']}")
    return result


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description='Face Recognition System')
    parser.add_argument('--train', action='store_true', help='Train the models')
    parser.add_argument('--check-duplicate', dest='check_duplicate', metavar='DIR',
                        help='Check a folder of captured faces against the existing model')
    parser.add_argument('--liveness-check', dest='liveness_check', metavar='IMG',
                        help='Diagnostic: print anti-spoof metrics for one image')
    parser.add_argument('--verify-liveness', dest='verify_liveness', metavar='DIR',
                        help='Check a burst of frames (frame_*.jpg) for a real blink and recognise')
    parser.add_argument('--algorithm', default='lbph', choices=['lbph', 'eigen', 'fisher'], help='Algorithm to use')
    parser.add_argument('--consensus', action='store_true', help='Require 2-of-3 algorithm agreement (anti-spoof)')
    parser.add_argument('image_path', nargs='?', help='Path to image for recognition')
    args = parser.parse_args()

    if args.train:
        success, message = train_models()
        result = {'success': success, 'message': message}
        print(json.dumps(result))
        if not success:
            sys.exit(1)
        sys.exit(0)
    elif args.check_duplicate:
        result = check_duplicate_face(args.check_duplicate, args.algorithm)
        print(json.dumps(result))
        sys.exit(0)
    elif args.liveness_check:
        result = spoof_check.liveness_report(args.liveness_check)
        print(json.dumps(result))
        sys.exit(0)
    elif args.verify_liveness:
        result = verify_liveness(args.verify_liveness, args.algorithm)
        print(json.dumps(result))
        sys.exit(0)
    elif args.image_path:
        if args.consensus:
            result = recognize_consensus_image(args.image_path)
        else:
            result = recognize_single_image(args.image_path, args.algorithm)
        print(json.dumps(result))
    else:
        start_recognition()