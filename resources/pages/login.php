<?php

//handle user login logics 



$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $userType = $_POST['user_type'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors['password'] = 'Password cannot be empty';
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        exit();
    }
    if ($userType == "administrator") {
        $stmt = $pdo->prepare("SELECT * FROM tbladmin WHERE emailAddress = :email");
    } elseif ($userType == "lecture") {
        $stmt = $pdo->prepare("SELECT * FROM tbllecture WHERE emailAddress = :email");
    } elseif ($userType == "student") {
        $stmt = $pdo->prepare("SELECT *, email as emailAddress, registrationNumber as Id FROM tblStudents WHERE email = :email");
    }
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();


    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user'] = [
            'id' => $user['Id'],
            'email' => $user['emailAddress'],
            'name' => $user['firstName'],
            'registrationNumber' => isset($user['registrationNumber']) ? $user['registrationNumber'] : null,
            'role' => $userType,
        ];

        header('Location: home');
        exit();
    } else {
        $errors['login'] = 'Invalid email or password';
        $_SESSION['errors'] = $errors;
    }
}
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
}


function display_error($error, $is_main = false)
{
    global $errors;
    if (isset($errors["{$error}"])) {

        echo '<div class="' . ($is_main ? 'error-main' : 'error') . '">
                  <p>' . $errors["{$error}"] . '</p>
           </div>';
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in to SAS Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="resources/assets/css/login_styles.css?v=<?php echo time(); ?>">
    <link rel="icon" href="resources/images/logo/face logo.png" />
</head>

<body>

    <body>

        <div class="split-layout">
            <div class="panel-left">
                <div class="branding">
                    <i class="fas fa-graduation-cap brand-icon"></i>
                    <h1>SAS College</h1>
                    <p>Advanced Attendance Platform</p>
                </div>
            </div>

            <div class="panel-right">
                <div class="form-container">
                    <a href="landing.php" class="logo">
                        <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Return to Home
                    </a>
                    <h1 class="form-title">Welcome Back</h1>
                    <p class="form-subtitle">Please enter your credentials to access the portal.</p>

                    <?php display_error('login', true); ?>

                    <form method="POST" action="">
                        <div class="input-group">
                            <i class="fas fa-user-shield"></i>
                            <select name="user_type" id="user_type" required>
                                <option value="" disabled selected hidden>Select Role</option>
                                <!-- <option value="student">Student</option> -->
                                <option value="lecture">Lecture / Teacher</option>
                                <option value="administrator">System Administrator</option>
                            </select>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" id="email" placeholder="Email Address" required>
                            <?php display_error('email'); ?>
                        </div>

                        <div class="input-group password">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" placeholder="Password" required>
                            <i id="eye" class="fa fa-eye"></i>
                            <?php display_error('password'); ?>
                        </div>

                        <button type="submit" class="btn" name="login">Sign In <i class="fas fa-sign-in-alt"
                                style="margin-left: 8px;"></i></button>
                    </form>
                </div>
            </div>
        </div>

        <script src="resources/assets/javascript/script.js"></script>
    </body>

</html>