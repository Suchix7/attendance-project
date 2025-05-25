import cv2
import os
import numpy as np

def check_face_detection():
    """Check face detection on all test images and save debug images"""
    print("\n=== Checking Face Detection in Test Images ===")
    
    # Get paths
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    test_dir = os.path.join(base_dir, 'test_images')
    cascade_path = os.path.join(base_dir, 'assets/haarcascade_frontalface_default.xml')
    debug_dir = os.path.join(test_dir, 'debug')
    
    # Create debug directory
    if not os.path.exists(debug_dir):
        os.makedirs(debug_dir)
    
    # Load face cascade
    face_cascade = cv2.CascadeClassifier(cascade_path)
    if face_cascade.empty():
        print("Error: Failed to load face cascade classifier")
        return
        
    # Process each test image
    total_images = 0
    successful_detections = 0
    
    for img_name in sorted(os.listdir(test_dir)):
        if not img_name.lower().endswith(('.jpg', '.jpeg', '.png')):
            continue
            
        total_images += 1
        img_path = os.path.join(test_dir, img_name)
        
        # Read image
        img = cv2.imread(img_path)
        if img is None:
            print(f"Failed to load {img_name}")
            continue
            
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        
        # Try different parameters for face detection
        parameters = [
            # (scaleFactor, minNeighbors, "description")
            (1.1, 3, "Standard"),
            (1.2, 3, "More lenient"),
            (1.3, 2, "Most lenient")
        ]
        
        best_faces = []
        best_params = None
        
        for scale_factor, min_neighbors, desc in parameters:
            faces = face_cascade.detectMultiScale(
                gray,
                scaleFactor=scale_factor,
                minNeighbors=min_neighbors,
                minSize=(30, 30),
                maxSize=(400, 400)
            )
            
            if len(faces) > 0:
                best_faces = faces
                best_params = (scale_factor, min_neighbors, desc)
                break
        
        # Create debug image
        debug_img = img.copy()
        
        if len(best_faces) > 0:
            successful_detections += 1
            scale, neighbors, desc = best_params
            print(f"✓ {img_name}: Found {len(best_faces)} face(s) with {desc} parameters (scale={scale}, neighbors={neighbors})")
            
            # Draw rectangles around detected faces
            for (x, y, w, h) in best_faces:
                cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 255, 0), 2)
                
        else:
            print(f"✗ {img_name}: No faces detected with any parameters")
            
        # Save debug image
        debug_path = os.path.join(debug_dir, f"debug_{img_name}")
        cv2.imwrite(debug_path, debug_img)
    
    # Print summary
    print(f"\n=== Detection Summary ===")
    print(f"Total images: {total_images}")
    print(f"Successful detections: {successful_detections}")
    print(f"Detection rate: {(successful_detections/total_images)*100:.1f}%")
    print(f"\nDebug images saved in: {debug_dir}")

if __name__ == '__main__':
    check_face_detection() 