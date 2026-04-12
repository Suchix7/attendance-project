<?php

if (isset($_POST["addLecture"])) {
    // Securely handle input
    $firstName = htmlspecialchars(trim($_POST["firstName"]));
    $lastName = htmlspecialchars(trim($_POST["lastName"]));
    $email = filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL);
    $phoneNumber = htmlspecialchars(trim($_POST["phoneNumber"]));
    $faculty = htmlspecialchars(trim($_POST["faculty"]));
    $dateRegistered = date("Y-m-d");
    $password = $_POST['password'];

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT); // Secure password hashing

    if ($email && $firstName && $lastName && $phoneNumber && $faculty) {
        try {
            // Check if lecture already exists
            $query = $pdo->prepare("SELECT * FROM tbllecture WHERE emailAddress = :email");
            $query->bindParam(':email', $email);
            $query->execute();

            if ($query->rowCount() > 0) {
                $_SESSION['message'] = "Lecture Already Exists";
            } else {
                // Insert new lecture
                $query = $pdo->prepare("INSERT INTO tbllecture 
                    (firstName, lastName, emailAddress, password, phoneNo, facultyCode, dateCreated) 
                    VALUES (:firstName, :lastName, :email, :password, :phoneNumber, :faculty, :dateCreated)");
                $query->bindParam(':firstName', $firstName);
                $query->bindParam(':lastName', $lastName);
                $query->bindParam(':email', $email);
                $query->bindParam(':password', $hashedPassword);
                $query->bindParam(':phoneNumber', $phoneNumber);
                $query->bindParam(':faculty', $faculty);
                $query->bindParam(':dateCreated', $dateRegistered);

                $query->execute();

                $_SESSION['message'] = "Lecture Added Successfully";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "Invalid input. Please check your data.";
    }
}

if (isset($_POST["editLecture"])) {
    $lectureId = filter_var($_POST["lectureId"], FILTER_VALIDATE_INT);
    $firstName = htmlspecialchars(trim($_POST["firstName"]));
    $lastName = htmlspecialchars(trim($_POST["lastName"]));
    $email = filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL);
    $phoneNumber = htmlspecialchars(trim($_POST["phoneNumber"]));
    $faculty = htmlspecialchars(trim($_POST["faculty"]));
    $password = $_POST["password"];

    if ($lectureId && $email && $firstName && $lastName && $phoneNumber && $faculty) {
        try {
            // Check if email exists for other lectures
            $query = $pdo->prepare("SELECT * FROM tbllecture WHERE emailAddress = :email AND Id != :id");
            $query->bindParam(':email', $email);
            $query->bindParam(':id', $lectureId);
            $query->execute();

            if ($query->rowCount() > 0) {
                $_SESSION['message'] = "Email already exists for another lecture";
            } else {
                // Prepare base query without password
                $sql = "UPDATE tbllecture SET 
                        firstName = :firstName,
                        lastName = :lastName,
                        emailAddress = :email,
                        phoneNo = :phoneNumber,
                        facultyCode = :faculty";

                // Add password to query if it's provided
                if (!empty($password)) {
                    $sql .= ", password = :password";
                }

                $sql .= " WHERE Id = :id";

                $query = $pdo->prepare($sql);

                // Bind parameters
                $params = [
                    ':firstName' => $firstName,
                    ':lastName' => $lastName,
                    ':email' => $email,
                    ':phoneNumber' => $phoneNumber,
                    ':faculty' => $faculty,
                    ':id' => $lectureId
                ];

                // Add password to parameters if provided
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $params[':password'] = $hashedPassword;
                }

                $query->execute($params);
                $_SESSION['message'] = "Lecture Updated Successfully";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['message'] = "Invalid input. Please check your data.";
    }
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="resources/images/logo/face logo.png" rel="icon">

    <title>AMS - Dashboard</title>
    <link rel="stylesheet" href="resources/assets/css/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css" rel="stylesheet">

</head>

<body>
    <?php include "Includes/topbar.php"; ?>

    <section class=main>

        <?php include "Includes/sidebar.php"; ?>

        <div class="main--content">
            <div id="overlay"></div>
            <?php showMessage() ?>
            <div class="table-container">
                <div class="title" id="showButton">
                    <h2 class="section--title">Lectures</h2>
                    <button class="add"><i class="ri-add-line"></i>Add lecture</button>
                </div>
                <div class="table">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email Address</th>
                                <th>Phone No</th>
                                <th>Faculty</th>
                                <th>Date Registered</th>
                                <th>Settings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM tbllecture";
                            $result = fetch($sql);
                            if ($result) {
                                foreach ($result as $row) {
                                    echo "<tr id='rowlecture{$row["Id"]}'>";
                                    echo "<td>" . $row["firstName"] . " " . $row["lastName"] . "</td>";
                                    echo "<td>" . $row["emailAddress"] . "</td>";
                                    echo "<td>" . $row["phoneNo"] . "</td>";
                                    echo "<td>" . $row["facultyCode"] . "</td>";
                                    echo "<td>" . $row["dateCreated"] . "</td>";
                                    echo "<td>
                                            <span>
                                                <i class='ri-edit-line edit' 
                                                   data-id='{$row["Id"]}' 
                                                   data-name='lecture'
                                                   data-firstname='{$row["firstName"]}'
                                                   data-lastname='{$row["lastName"]}'
                                                   data-email='{$row["emailAddress"]}'
                                                   data-phone='{$row["phoneNo"]}'
                                                   data-faculty='{$row["facultyCode"]}'></i>
                                                <i class='ri-delete-bin-line delete' data-id='{$row["Id"]}' data-name='lecture'></i>
                                            </span>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6'>No records found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="formDiv" id="form" style="display:none; ">
                <form method="POST" action="" name="addLecture" enctype="multipart/form-data">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Add Lecture</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <input type="text" name="firstName" placeholder="First Name" required>
                    <input type="text" name="lastName" placeholder="Last Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="text" name="phoneNumber" placeholder="Phone Number" required>
                    <input type="password" name="password" placeholder="**********" required>

                    <select required name="faculty">
                        <option value="" selected>Select Faculty</option>
                        <?php
                        $facultyNames = getFacultyNames();
                        foreach ($facultyNames as $faculty) {
                            echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" class="submit" value="Save Lecture" name="addLecture">
                </form>
            </div>

            <!-- Edit Form -->
            <div class="formDiv" id="editForm" style="display:none; height: 500px;">
                <form method="POST" action="" name="editLecture" enctype="multipart/form-data">
                    <div style="display:flex; justify-content:space-around;">
                        <div class="form-title">
                            <p>Edit Lecture</p>
                        </div>
                        <div>
                            <span class="close">&times;</span>
                        </div>
                    </div>
                    <input type="hidden" name="lectureId" id="editLectureId">
                    <input type="text" name="firstName" id="editFirstName" placeholder="First Name" required>
                    <input type="text" name="lastName" id="editLastName" placeholder="Last Name" required>
                    <input type="email" name="email" id="editEmail" placeholder="Email Address" required>
                    <input type="text" name="phoneNumber" id="editPhoneNumber" placeholder="Phone Number" required>
                    <input type="password" name="password" id="editPassword"
                        placeholder="New Password (leave empty to keep current)">
                    <select required name="faculty" id="editFaculty">
                        <option value="">Select Faculty</option>
                        <?php
                        $facultyNames = getFacultyNames();
                        foreach ($facultyNames as $faculty) {
                            echo '<option value="' . $faculty["facultyCode"] . '">' . $faculty["facultyName"] . '</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" class="submit" value="Update Lecture" name="editLecture">
                </form>
            </div>



    </section>

    <?php js_asset(["admin_functions", "active_link", "delete_request", "script"]) ?>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addForm = document.getElementById('form');
            const editForm = document.getElementById('editForm');
            const overlay = document.getElementById('overlay');
            const showButton = document.querySelector('#showButton .add');
            const closeButtons = document.querySelectorAll('.close');

            // Show add form
            showButton.addEventListener('click', function () {
                addForm.style.display = 'block';
                overlay.style.display = 'block';
            });

            // Handle edit button clicks
            document.querySelectorAll('.edit').forEach(function (editIcon) {
                editIcon.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');

                    // Populate the edit form
                    document.getElementById('editLectureId').value = id;
                    document.getElementById('editFirstName').value = this.getAttribute('data-firstname');
                    document.getElementById('editLastName').value = this.getAttribute('data-lastname');
                    document.getElementById('editEmail').value = this.getAttribute('data-email');
                    document.getElementById('editPhoneNumber').value = this.getAttribute('data-phone');
                    document.getElementById('editFaculty').value = this.getAttribute('data-faculty');
                    document.getElementById('editPassword').value = ''; // Clear password field

                    // Show the edit form
                    editForm.style.display = 'block';
                    overlay.style.display = 'block';
                });
            });

            // Close forms
            closeButtons.forEach(function (closeButton) {
                closeButton.addEventListener('click', function () {
                    addForm.style.display = 'none';
                    editForm.style.display = 'none';
                    overlay.style.display = 'none';
                });
            });

            // Close forms when clicking overlay
            overlay.addEventListener('click', function () {
                addForm.style.display = 'none';
                editForm.style.display = 'none';
                overlay.style.display = 'none';
            });
        });
    </script>

</body>

</html>