import cv2
import numpy as np
import os
import sys
import json
from train_test_lbph import test_recognition, train_lbph

def save_debug_image(img, faces, filename, selected_face=None):
    """Save an image with detected faces marked for debugging"""
    debug_img = img.copy()
    for i, (x, y, w, h) in enumerate(faces):
        color = (0, 255, 0) if selected_face is not None and i == selected_face else (0, 0, 255)
        cv2.rectangle(debug_img, (x, y), (x+w, y+h), color, 2)
        cv2.putText(debug_img, f"Face {i+1}", (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
    cv2.imwrite(filename, debug_img)

def get_largest_face(faces):
    """Get the index of the largest face"""
    if len(faces) == 0:
        return None
    largest_area = 0
    largest_index = 0
    for i, (x, y, w, h) in enumerate(faces):
        area = w * h
        if area > largest_area:
            largest_area = area
            largest_index = i
    return largest_index

def test_multiple_images(test_dir):
    """Test face recognition on multiple images in a directory"""
    try:
        # First train the model
        print("Training LBPH model...")
        train_result = train_lbph()
        if not train_result['success']:
            print(f"Error training model: {train_result['message']}")
            return
        print(f"Training complete: {train_result['message']}")
        print("\nTesting images...")
        
        results = []
        face_cascade = cv2.CascadeClassifier(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'assets/haarcascade_frontalface_default.xml'))
        
        # Get all jpg files in the directory
        image_files = [f for f in os.listdir(test_dir) if f.lower().endswith('.jpg') and not f.startswith('debug_') and not f.startswith('recognition_') and not f.startswith('face_roi_')]
        
        for image_file in sorted(image_files):
            image_path = os.path.join(test_dir, image_file)
            print(f"\nTesting image: {image_file}")
            
            # Read image and check face detection
            img = cv2.imread(image_path)
            if img is None:
                print(f"Error: Could not read image {image_file}")
                continue
                
            gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
            gray = cv2.equalizeHist(gray)
            
            # More strict face detection parameters
            faces = face_cascade.detectMultiScale(
                gray,
                scaleFactor=1.1,  # More gradual scaling
                minNeighbors=5,   # More strict neighborhood check
                minSize=(50, 50), # Larger minimum face size
                maxSize=(300, 300)
            )
            
            print(f"Number of faces detected: {len(faces)}")
            
            if len(faces) == 0:
                print("No faces detected in image")
                results.append({
                    'success': False,
                    'message': 'No faces detected',
                    'image_name': image_file
                })
                continue
                
            # Select largest face
            best_face_index = get_largest_face(faces)
            if best_face_index is None:
                print("Could not determine best face")
                continue
                
            # Save debug image with face detection
            debug_path = os.path.join(test_dir, f"debug_{image_file}")
            save_debug_image(img, faces, debug_path, best_face_index)
            print(f"Debug image saved as: {debug_path}")
            
            # Extract best face for recognition
            x, y, w, h = faces[best_face_index]
            face_roi = gray[y:y+h, x:x+w]
            face_roi = cv2.resize(face_roi, (100, 100))
            face_roi = cv2.GaussianBlur(face_roi, (5, 5), 0)
            
            # Save face ROI for debugging
            face_debug_path = os.path.join(test_dir, f"face_roi_{image_file}")
            cv2.imwrite(face_debug_path, face_roi)
            print(f"Face ROI saved as: {face_debug_path}")
            
            result = test_recognition(image_path)
            result['image_name'] = image_file
            results.append(result)
            
            if result['success']:
                print(f"Result: {result['message']}")
                print(f"Result image saved as: {result['result_image']}")
            else:
                print(f"Error: {result['message']}")
        
        return results
        
    except Exception as e:
        print(f"Error: {str(e)}")
        return []

if __name__ == '__main__':
    # Get the absolute path to the test directory
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    test_dir = os.path.join(base_dir, 'resources', 'for test')
    
    if not os.path.exists(test_dir):
        print(f"Test directory not found: {test_dir}")
        sys.exit(1)
    
    print(f"Testing images in: {test_dir}")
    results = test_multiple_images(test_dir)
    
    if results:
        # Print summary
        print("\nSummary:")
        print("-" * 80)
        for result in results:
            if result['success']:
                print(f"{result['image_name']}: {result['predicted_student_id']} ({result['confidence']:.1f}% confidence)")
            else:
                print(f"{result['image_name']}: Failed - {result['message']}")
        print("-" * 80) 