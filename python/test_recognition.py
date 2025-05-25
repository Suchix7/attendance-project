import cv2
import os
import json
from pathlib import Path
from face_recognition_with_existing_detection import train_lbph_model, recognize_face

def test_recognition():
    """Test face recognition on detected faces"""
    print("\n=== Training Model ===")
    train_result = train_lbph_model()
    if not train_result['success']:
        print(f"Training failed: {train_result['message']}")
        return
    
    print(f"✓ Model trained successfully with {train_result['num_faces']} faces from {train_result['num_students']} students")
    
    # Get test images directory
    base_dir = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    test_dir = base_dir / 'test_images'
    
    if not test_dir.exists():
        print(f"\nTest directory not found at: {test_dir}")
        return
    
    # Initialize results
    results = {
        'student45': {'correct': 0, 'total': 0, 'avg_confidence': 0},
        'student99': {'correct': 0, 'total': 0, 'avg_confidence': 0},
        'random': {'correct_unknown': 0, 'total': 0}
    }
    
    # Test recognition
    print("\n=== Testing Recognition ===")
    
    # Test student 45 images
    print("\nTesting Student 45 images:")
    for img_path in sorted(test_dir.glob('test45_*.jpg')):
        results['student45']['total'] += 1
        result = recognize_face(str(img_path))
        if result['success']:
            is_correct = result['predicted_student_id'] == '45'
            mark = '✓' if is_correct else '✗'
            print(f"{mark} {img_path.name}: Recognized as {result['predicted_student_id']} ({result['confidence']:.1f}% confidence)")
            if is_correct:
                results['student45']['correct'] += 1
                results['student45']['avg_confidence'] += result['confidence']
    
    # Test student 99 images
    print("\nTesting Student 99 images:")
    for img_path in sorted(test_dir.glob('test99_*.jpg')):
        results['student99']['total'] += 1
        result = recognize_face(str(img_path))
        if result['success']:
            is_correct = result['predicted_student_id'] == '99'
            mark = '✓' if is_correct else '✗'
            print(f"{mark} {img_path.name}: Recognized as {result['predicted_student_id']} ({result['confidence']:.1f}% confidence)")
            if is_correct:
                results['student99']['correct'] += 1
                results['student99']['avg_confidence'] += result['confidence']
    
    # Test random images
    print("\nTesting Random images:")
    for img_path in sorted(test_dir.glob('random*.jpg')):
        results['random']['total'] += 1
        result = recognize_face(str(img_path))
        if result['success']:
            is_correct = result['predicted_student_id'] == 'Unknown'
            mark = '✓' if is_correct else '✗'
            print(f"{mark} {img_path.name}: Recognized as {result['predicted_student_id']} ({result['confidence']:.1f}% confidence)")
            if is_correct:
                results['random']['correct_unknown'] += 1
    
    # Calculate averages
    if results['student45']['correct'] > 0:
        results['student45']['avg_confidence'] /= results['student45']['correct']
    if results['student99']['correct'] > 0:
        results['student99']['avg_confidence'] /= results['student99']['correct']
    
    # Print summary
    print("\n=== Recognition Summary ===")
    print(f"\nStudent 45:")
    print(f"- Correct recognitions: {results['student45']['correct']}/{results['student45']['total']} ({results['student45']['correct']/results['student45']['total']*100:.1f}%)")
    if results['student45']['correct'] > 0:
        print(f"- Average confidence for correct matches: {results['student45']['avg_confidence']:.1f}%")
    
    print(f"\nStudent 99:")
    print(f"- Correct recognitions: {results['student99']['correct']}/{results['student99']['total']} ({results['student99']['correct']/results['student99']['total']*100:.1f}%)")
    if results['student99']['correct'] > 0:
        print(f"- Average confidence for correct matches: {results['student99']['avg_confidence']:.1f}%")
    
    print(f"\nRandom Images:")
    print(f"- Correct rejections: {results['random']['correct_unknown']}/{results['random']['total']} ({results['random']['correct_unknown']/results['random']['total']*100:.1f}%)")
    
    # Calculate overall accuracy
    total_tests = results['student45']['total'] + results['student99']['total'] + results['random']['total']
    total_correct = results['student45']['correct'] + results['student99']['correct'] + results['random']['correct_unknown']
    print(f"\nOverall Accuracy: {total_correct}/{total_tests} ({total_correct/total_tests*100:.1f}%)")

if __name__ == '__main__':
    test_recognition() 