<?php


if (isset($_POST["addVenue"])) {
    // Sanitize and validate inputs
    $className = htmlspecialchars(trim($_POST['className']));
    $facultyCode = htmlspecialchars(trim($_POST['faculty']));
    $currentStatus = htmlspecialchars(trim($_POST['currentStatus']));
    $capacity = filter_var($_POST['capacity'], FILTER_VALIDATE_INT);
    $classification = htmlspecialchars(trim($_POST['classification']));
    $latitude = htmlspecialchars(trim($_POST['latitude']));
    $longitude = htmlspecialchars(trim($_POST['longitude']));

    // Check for required fields
    if (!$className || !$facultyCode || !$currentStatus || !$capacity || !$classification || !$latitude || !$longitude) {
        $_SESSION['message'] = "All fields are required and must be valid.";
    } else {
        $dateRegistered = date("Y-m-d");

        // Prepare database operations using PDO
        try {
            // Check if venue already exists
            $stmt = $pdo->prepare("SELECT * FROM tblvenue WHERE className = :className");
            $stmt->bindParam(':className', $className);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = "Venue Already Exists";
            } else {
                // Insert the new venue
                $stmt = $pdo->prepare(
                    "INSERT INTO tblvenue (className, facultyCode, currentStatus, capacity, classification, latitude, longitude, dateCreated)
                    VALUES (:className, :facultyCode, :currentStatus, :capacity, :classification, :latitude, :longitude, :dateCreated)"
                );
                $stmt->bindParam(':className', $className);
                $stmt->bindParam(':facultyCode', $facultyCode);
                $stmt->bindParam(':currentStatus', $currentStatus);
                $stmt->bindParam(':capacity', $capacity, PDO::PARAM_INT);
                $stmt->bindParam(':classification', $classification);
                $stmt->bindParam(':latitude', $latitude);
                $stmt->bindParam(':longitude', $longitude);
                $stmt->bindParam(':dateCreated', $dateRegistered);

                if ($stmt->execute()) {
                    $_SESSION['message'] = "Venue Inserted Successfully";
                } else {
                    $_SESSION['message'] = "Failed to Insert Venue.";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Database Error: " . $e->getMessage();
        }
    }
}

// Add edit handler for venues
if (isset($_POST["editVenue"])) {
    $venueId = filter_var($_POST["venueId"], FILTER_VALIDATE_INT);
    $className = htmlspecialchars(trim($_POST['className']));
    $facultyCode = htmlspecialchars(trim($_POST['faculty']));
    $currentStatus = htmlspecialchars(trim($_POST['currentStatus']));
    $capacity = filter_var($_POST['capacity'], FILTER_VALIDATE_INT);
    $classification = htmlspecialchars(trim($_POST['classification']));
    $latitude = htmlspecialchars(trim($_POST['latitude']));
    $longitude = htmlspecialchars(trim($_POST['longitude']));

    if ($venueId && $className && $facultyCode && $currentStatus && $capacity && $classification && $latitude && $longitude) {
        try {
            // Update the venue
            $stmt = $pdo->prepare(
                "UPDATE tblvenue SET 
                className = :className,
                facultyCode = :facultyCode,
                currentStatus = :currentStatus,
                capacity = :capacity,
                classification = :classification,
                latitude = :latitude,
                longitude = :longitude
                WHERE Id = :id"
            );

            $stmt->execute([
                ':className' => $className,
                ':facultyCode' => $facultyCode,
                ':currentStatus' => $currentStatus,
                ':capacity' => $capacity,
                ':classification' => $classification,
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':id' => $venueId
            ]);

            $_SESSION['message'] = "Venue Updated Successfully";
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error updating venue: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "All fields are required and must be valid.";
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">
    <title>Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/topbar.php' ?>
    <section class="main">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main--content">

            <div id="overlay"></div>

            <div class="rooms">
                <div class="title">
                    <h2 class="section--title">Rooms</h2>
                    <div class="rooms--right--btns">
                        <select name="date" id="date" class="dropdown room--filter">
                            <option>Filter</option>
                            <option value="free">Free</option>
                            <option value="scheduled">Scheduled</option>
                        </select>
                        <button id="addClass1" class="add show-form"><i class="ri-add-line"></i>Add lecture
                            room</button>
                    </div>
                </div>
                <div class="rooms--cards">
                    <a href="#" class="room--card">
                        <div class="img--box--cover">
                            <div class="img--box">
                                <img src="resources/images/office image.jpeg" alt="">
                            </div>
                        </div>
                        <p class="free">Office</p>
                    </a>
                    <a href="#" class="room--card">
                        <div class="img--box--cover">
                            <div class="img--box">
                                <img src="resources/images/class.jpeg" alt="">
                            </div>
                        </div>
                        <p class="free">Class</p>
                    </a>

                    <a href="#" class="room--card">
                        <div class="img--box--cover">
                            <div class="img--box">
                                <img src="resources/images/lecture hall.jpeg" alt="">
                            </div>
                        </div>
                        <p class="free">Lecture Hall</p>
                    </a>

                    <a href="#" class="room--card">
                        <div class="img--box--cover">
                            <div class="img--box">
                                <img src="resources/images/computer lab.jpeg" alt="">
                            </div>
                        </div>
                        <p class="free">Computer Lab</p>
                    </a>
                    <a href="#" class="room--card">
                        <div class="img--box--cover">
                            <div class="img--box">
                                <img src="resources/images/laboratory.jpeg" alt="">
                            </div>
                        </div>
                        <p class="free">Science Lab</p>
                    </a>
                </div>
            </div>
            <?php showMessage() ?>
            <div class="table-container">
                <div class="title" id="addClass2">
                    <h2 class="section--title">Lecture Rooms</h2>
                    <button class="add show-form"><i class="ri-add-line"></i>Add Class</button>
                </div>

                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Faculty</th>
                                <th>Current Status</th>
                                <th>Capacity</th>
                                <th>Classification</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Settings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM tblvenue";
                            $stmt = $pdo->query($sql);
                            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowvenue{$row["Id"]}'>";
                                    echo "<td>" . $row["className"] . "</td>";
                                    echo "<td>" . $row["facultyCode"] . "</td>";
                                    echo "<td>" . $row["currentStatus"] . "</td>";
                                    echo "<td>" . $row["capacity"] . "</td>";
                                    echo "<td>" . $row["classification"] . "</td>";
                                    echo "<td>" . $row["latitude"] . "</td>";
                                    echo "<td>" . $row["longitude"] . "</td>";
                                    echo "<td>
                                            <div class='venue-settings'>
                                                <i class='ri-edit-line edit' 
                                                   data-id='{$row["Id"]}' 
                                                   data-name='venue'
                                                   data-classname='{$row["className"]}'
                                                   data-faculty='{$row["facultyCode"]}'
                                                   data-status='{$row["currentStatus"]}'
                                                   data-capacity='{$row["capacity"]}'
                                                   data-classification='{$row["classification"]}'
                                                   data-latitude='{$row["latitude"]}'
                                                   data-longitude='{$row["longitude"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='venue'></i>
                                            </div>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='8'>No records found</td></tr>";
                            }

                            ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <div class="formDiv" id="addClassForm" style="display:none ">
                <form method="POST" action="" name="addVenue" enctype="multipart/form-data">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Add Venue</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <div class="input-with-icon">
                        <i class="ri-building-line"></i>
                        <input type="text" name="className" placeholder="Class Name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-checkbox-circle-line"></i>
                            <select name="currentStatus" required>
                                <option value="">--Current Status--</option>
                                <option value="availlable">Available</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-group-line"></i>
                            <input type="text" name="capacity" placeholder="Capacity" required>
                        </div>
                    </div>

                    <button type="button" class="gps-btn" onclick="autoFillGPS('addClassForm')">
                        <i class="ri-map-pin-line"></i> Auto-fill Current GPS Location
                    </button>

                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-map-pin-2-line"></i>
                            <input type="text" name="latitude" id="addLatitude" placeholder="Latitude" required>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-map-pin-2-fill"></i>
                            <input type="text" name="longitude" id="addLongitude" placeholder="Longitude" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-list-check"></i>
                            <select required name="classification">
                                <option value="" selected> --Select Class Type--</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="computerLab">Computer Lab</option>
                                <option value="lectureHall">Lecture Hall</option>
                                <option value="class">Class</option>
                                <option value="office">Office</option>
                            </select>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-government-line"></i>
                            <select required name="faculty">
                                <option value="" selected>Select Faculty</option>
                                <?php
                                $facultyNames = getFacultyNames();
                                foreach ($facultyNames as $faculty) {
                                    echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <input type="submit" class="submit" value="Save Venue" name="addVenue">
                </form>
            </div>

            <!-- Add Edit Form -->
            <div class="formDiv" id="editClassForm" style="display:none">
                <form method="POST" action="" name="editVenue" enctype="multipart/form-data">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Edit Venue</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <input type="hidden" name="venueId" id="editVenueId">
                    <div class="input-with-icon">
                        <i class="ri-building-line"></i>
                        <input type="text" name="className" id="editClassName" placeholder="Class Name" required>
                    </div>

                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-checkbox-circle-line"></i>
                            <select name="currentStatus" id="editCurrentStatus" required>
                                <option value="">--Current Status--</option>
                                <option value="availlable">Available</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-group-line"></i>
                            <input type="text" name="capacity" id="editCapacity" placeholder="Capacity" required>
                        </div>
                    </div>

                    <button type="button" class="gps-btn" onclick="autoFillGPS('editClassForm')">
                        <i class="ri-map-pin-line"></i> Update with Current GPS Location
                    </button>

                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-map-pin-2-line"></i>
                            <input type="text" name="latitude" id="editLatitude" placeholder="Latitude" required>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-map-pin-2-fill"></i>
                            <input type="text" name="longitude" id="editLongitude" placeholder="Longitude" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-with-icon">
                            <i class="ri-list-check"></i>
                            <select required name="classification" id="editClassification">
                                <option value=""> --Select Class Type--</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="computerLab">Computer Lab</option>
                                <option value="lectureHall">Lecture Hall</option>
                                <option value="class">Class</option>
                                <option value="office">Office</option>
                            </select>
                        </div>
                        <div class="input-with-icon">
                            <i class="ri-government-line"></i>
                            <select required name="faculty" id="editFaculty">
                                <option value="">Select Faculty</option>
                                <?php
                                $facultyNames = getFacultyNames();
                                foreach ($facultyNames as $faculty) {
                                    echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <input type="submit" class="submit" value="Update Venue" name="editVenue">
                </form>
            </div>
        </div>
    </section>
    <?php js_asset(["active_link", "delete_request"]) ?>



    <script>
        const show_form = document.querySelectorAll(".show-form")
        const addClassForm = document.getElementById('addClassForm');
        const editClassForm = document.getElementById('editClassForm');
        const overlay = document.getElementById('overlay');
        const closeButtons = document.querySelectorAll('.close');

        show_form.forEach((showForm) => {
            showForm.addEventListener('click', function () {
                addClassForm.style.display = 'block';
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        });

        // Handle edit button clicks
        document.querySelectorAll('.edit').forEach(function (editIcon) {
            editIcon.addEventListener('click', function () {
                const id = this.getAttribute('data-id');

                // Populate the edit form
                document.getElementById('editVenueId').value = id;
                document.getElementById('editClassName').value = this.getAttribute('data-classname');
                document.getElementById('editCurrentStatus').value = this.getAttribute('data-status');
                document.getElementById('editCapacity').value = this.getAttribute('data-capacity');
                document.getElementById('editClassification').value = this.getAttribute('data-classification');
                document.getElementById('editLatitude').value = this.getAttribute('data-latitude');
                document.getElementById('editLongitude').value = this.getAttribute('data-longitude');
                document.getElementById('editFaculty').value = this.getAttribute('data-faculty');

                // Show the edit form
                editClassForm.style.display = 'block';
                overlay.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        });

        // Close both add and edit forms
        closeButtons.forEach(function (closeButton) {
            closeButton.addEventListener('click', function () {
                addClassForm.style.display = 'none';
                editClassForm.style.display = 'none';
                overlay.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        });

        // GPS Functions
        function autoFillGPS(formId) {
            const btn = document.querySelector(`#${formId} .gps-btn`);
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Getting Location...';
            btn.disabled = true;

            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude.toFixed(8);
                        const lng = position.coords.longitude.toFixed(8);
                        
                        if (formId === 'addClassForm') {
                            document.getElementById('addLatitude').value = lat;
                            document.getElementById('addLongitude').value = lng;
                        } else {
                            document.getElementById('editLatitude').value = lat;
                            document.getElementById('editLongitude').value = lng;
                        }
                        
                        btn.innerHTML = '<i class="ri-checkbox-circle-line"></i> Location Captured!';
                        btn.style.backgroundColor = '#dcfce7';
                        btn.style.color = '#166534';
                        btn.style.borderColor = '#22c55e';
                        
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                            btn.style.backgroundColor = '';
                            btn.style.color = '';
                            btn.style.borderColor = '';
                            btn.disabled = false;
                        }, 3000);
                    },
                    function(error) {
                        alert("Error getting location: " + error.message);
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    },
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
                );
            } else {
                alert("Geolocation is not supported by your browser.");
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>

</html>