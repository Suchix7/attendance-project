import cv2
import sys
import json
import os

def test_face_detection(image_path):
    # Get the cascade file path
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
    
    # Load the cascade
    face_cascade = cv2.CascadeClassifier(cascade_path)
    if face_cascade.empty():
        return {'error': 'Failed to load cascade classifier'}
    
    # Read the image
    img = cv2.imread(image_path)
    if img is None:
        return {'error': f'Failed to load image from {image_path}'}
    
    # Convert to grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    
    # Detect faces
    faces = face_cascade.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=3,
        minSize=(30, 30),
        maxSize=(300, 300)
    )
    
    # Return results
    result = {
        'faces_found': len(faces),
        'faces': [
            {'x': int(x), 'y': int(y), 'width': int(w), 'height': int(h)}
            for (x, y, w, h) in faces
        ],
        'image_size': {'width': img.shape[1], 'height': img.shape[0]}
    }
    
    return result

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Please provide an image path'}))
        sys.exit(1)
    
    result = test_face_detection(sys.argv[1])
    print(json.dumps(result, indent=2)) 