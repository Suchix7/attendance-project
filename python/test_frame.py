import cv2
import json
import os

def save_test_frame():
    # Start video capture
    cap = cv2.VideoCapture(0)
    if not cap.isOpened():
        print("Error: Could not open webcam")
        return False
    
    print("Capturing test frame... Please look at the camera")
    
    # Wait a bit for the camera to initialize
    for i in range(10):
        ret = cap.read()[0]
        if not ret:
            continue
    
    # Capture frame
    ret, frame = cap.read()
    cap.release()
    
    if not ret:
        print("Error: Could not capture frame")
        return False
    
    # Save frame
    cv2.imwrite('test_frame.jpg', frame)
    print("Test frame saved as 'test_frame.jpg'")
    return True

def test_face_detection():
    # Get the cascade file path
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
    
    # Load the cascade
    face_cascade = cv2.CascadeClassifier(cascade_path)
    if face_cascade.empty():
        print("Error: Could not load face cascade classifier")
        return
    
    # Read the test frame
    frame = cv2.imread('test_frame.jpg')
    if frame is None:
        print("Error: Could not read test frame")
        return
    
    # Convert to grayscale
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    
    # Detect faces
    faces = face_cascade.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=3,
        minSize=(30, 30),
        maxSize=(300, 300)
    )
    
    # Draw rectangles around faces
    for (x, y, w, h) in faces:
        cv2.rectangle(frame, (x, y), (x+w, y+h), (0, 255, 0), 2)
        cv2.putText(frame, f'Face detected', (x, y-10), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 255, 0), 2)
    
    # Show results
    print(f"Found {len(faces)} faces")
    
    # Save result
    cv2.imwrite('test_result.jpg', frame)
    print("Result saved as 'test_result.jpg'")
    
    # Return detection result
    result = {
        'success': True,
        'face_detected': len(faces) > 0,
        'faces_found': len(faces),
        'faces': [
            {'x': int(x), 'y': int(y), 'width': int(w), 'height': int(h)}
            for (x, y, w, h) in faces
        ]
    }
    
    print("Detection result:")
    print(json.dumps(result, indent=2))

if __name__ == '__main__':
    if save_test_frame():
        test_face_detection() 