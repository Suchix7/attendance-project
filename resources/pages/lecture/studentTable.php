<div class="table">
    <table>
        <thead>
            <tr>
                <th>Registration No</th>
                <th>Name</th>
                <th>Course</th>
                <th>Unit</th>
                <th>Venue</th>
                <th>Attendance</th>
                <th>Settings</th>
            </tr>
        </thead>
        <tbody id="studentTableBody">
            <?php
            if (isset($_POST['courseID']) && isset($_POST['unitID']) && isset($_POST['venueID'])) {

                $courseID = $_POST['courseID'];
                $unitID = $_POST['unitID'];
                $venueID = $_POST['venueID'];

                $sql = "SELECT s.*, a.attendanceStatus 
                        FROM tblStudents s 
                        LEFT JOIN tblattendance a ON s.registrationNumber = a.studentRegistrationNumber 
                        AND a.course = :courseID 
                        AND a.unit = :unitID 
                        AND a.dateMarked = CURDATE()
                        WHERE s.courseCode = :courseID2";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':courseID' => $courseID,
                    ':unitID' => $unitID,
                    ':courseID2' => $courseID
                ]);
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($result) {
                    foreach ($result as $row) {
                        $registrationNumber = $row["registrationNumber"];
                        $status = !empty($row["attendanceStatus"]) ? $row["attendanceStatus"] : "Absent";
                        $statusClass = strtolower($status) === "present" ? "attendance-status present" : "attendance-status";
                        
                        echo "<tr data-student-id='{$registrationNumber}'>";
                        echo "<td class='student-id'>" . $registrationNumber . "</td>";
                        echo "<td>" . $row["firstName"] . " " . $row["lastName"] . "</td>";
                        echo "<td>" . $courseID . "</td>";
                        echo "<td>" . $unitID . "</td>";
                        echo "<td>" . $venueID . "</td>";
                        echo "<td class='{$statusClass}'>" . $status . "</td>";
                        echo "<td><span><i class='ri-delete-bin-line delete' onclick='confirmMarkAbsent(this, \"$registrationNumber\", \"$courseID\", \"$unitID\")'></i></span></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7'>No records found</td></tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>