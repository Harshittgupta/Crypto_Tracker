<?php
// Direct admin account fix script
include("includes/config.php");

// Display all errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Admin Account Fix Tool</h1>";

// Step 1: Check database connection
echo "<h2>Step 1: Database Connection</h2>";
if ($conn) {
    echo "<p style='color:green'>✓ Connected to database successfully</p>";
} else {
    echo "<p style='color:red'>✗ Database connection failed: " . mysqli_connect_error() . "</p>";
    exit("Cannot continue without database connection");
}

// Step 2: Check if the users table exists
echo "<h2>Step 2: Verify Users Table</h2>";
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p style='color:green'>✓ Users table exists</p>";
} else {
    echo "<p style='color:red'>✗ Users table doesn't exist!</p>";
    echo "<p>Creating users table...</p>";
    $create_table = $conn->query("
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME NULL
        )
    ");
    
    if ($create_table) {
        echo "<p style='color:green'>✓ Users table created successfully</p>";
    } else {
        echo "<p style='color:red'>✗ Failed to create users table: " . $conn->error . "</p>";
        exit("Cannot continue without users table");
    }
}

// Step 3: Check the structure of the users table
echo "<h2>Step 3: Check Users Table Structure</h2>";
$table_structure = $conn->query("DESCRIBE users");
if (!$table_structure) {
    echo "<p style='color:red'>✗ Error checking table structure: " . $conn->error . "</p>";
} else {
    // Check for is_admin column
    $has_admin_column = false;
    $correct_admin_type = false;
    
    while ($column = $table_structure->fetch_assoc()) {
        if ($column['Field'] == 'is_admin') {
            $has_admin_column = true;
            if (strpos(strtolower($column['Type']), 'tinyint') !== false) {
                $correct_admin_type = true;
            }
            echo "<p>Found is_admin column with type: " . $column['Type'] . "</p>";
            break;
        }
    }
    
    if (!$has_admin_column) {
        echo "<p style='color:red'>✗ is_admin column is missing!</p>";
        echo "<p>Adding is_admin column...</p>";
        $add_column = $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
        if ($add_column) {
            echo "<p style='color:green'>✓ Added is_admin column</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to add is_admin column: " . $conn->error . "</p>";
        }
    } elseif (!$correct_admin_type) {
        echo "<p style='color:red'>✗ is_admin column has wrong data type!</p>";
        echo "<p>Fixing is_admin column type...</p>";
        $fix_column = $conn->query("ALTER TABLE users MODIFY COLUMN is_admin TINYINT(1) DEFAULT 0");
        if ($fix_column) {
            echo "<p style='color:green'>✓ Fixed is_admin column type</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to fix is_admin column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color:green'>✓ is_admin column exists and has correct type</p>";
    }
}

// Step 4: Check for existing admin accounts
echo "<h2>Step 4: Check Admin Accounts</h2>";
$admin_check = $conn->query("SELECT id, username, email FROM users WHERE is_admin = 1");
if (!$admin_check) {
    echo "<p style='color:red'>✗ Error checking admin accounts: " . $conn->error . "</p>";
} else {
    if ($admin_check->num_rows > 0) {
        echo "<p style='color:green'>✓ Found " . $admin_check->num_rows . " admin account(s)</p>";
        echo "<ul>";
        while ($admin = $admin_check->fetch_assoc()) {
            echo "<li>ID: " . $admin['id'] . ", Username: " . $admin['username'] . ", Email: " . $admin['email'] . "</li>";
            
            // Reset this admin's password
            $new_password = "Admin123!";
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $reset = $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = " . $admin['id']);
            if ($reset) {
                echo " - <span style='color:green'>Password reset to: $new_password</span>";
            } else {
                echo " - <span style='color:red'>Failed to reset password: " . $conn->error . "</span>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>✗ No admin accounts found</p>";
        echo "<p>Creating new admin account...</p>";
        
        // Create admin account
        $admin_username = "admin";
        $admin_email = "admin@example.com";
        $admin_password = "Admin123!";
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $create_admin = $conn->query("INSERT INTO users (username, email, password, is_admin) VALUES ('$admin_username', '$admin_email', '$hashed_password', 1)");
        if ($create_admin) {
            echo "<p style='color:green'>✓ Created admin account:</p>";
            echo "<ul>";
            echo "<li>Username: admin</li>";
            echo "<li>Email: admin@example.com</li>";
            echo "<li>Password: Admin123!</li>";
            echo "</ul>";
        } else {
            echo "<p style='color:red'>✗ Failed to create admin account: " . $conn->error . "</p>";
        }
    }
}

// Step 5: Fix login.php file
echo "<h2>Step 5: Check login.php File</h2>";
$login_file = file_get_contents("login.php");
if ($login_file === false) {
    echo "<p style='color:red'>✗ Could not read login.php file</p>";
} else {
    // Check for common issues
    $issues_found = false;
    
    // Check admin session variable
    if (strpos($login_file, "\$_SESSION['admin']") === false) {
        echo "<p style='color:red'>✗ login.php doesn't set admin session variable</p>";
        $issues_found = true;
    }
    
    // Check for correct is_admin check
    if (strpos($login_file, "is_admin") === false) {
        echo "<p style='color:red'>✗ login.php doesn't check is_admin field</p>";
        $issues_found = true;
    }
    
    // Check for correct session handling
    if (strpos($login_file, "session_start()") === false) {
        echo "<p style='color:red'>✗ login.php doesn't start session</p>";
        $issues_found = true;
    }
    
    if (!$issues_found) {
        echo "<p style='color:green'>✓ login.php appears to have necessary code for admin login</p>";
    }
    
    // Get new login.php content - this is a basic version to ensure admin login works
    $new_login_content = '<?php
include("includes/config.php");
session_start();

// If already logged in, redirect
if (isset($_SESSION["user_id"])) {
    if (isset($_SESSION["admin"]) && $_SESSION["admin"] === true) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];
    
    $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["admin"] = ($user["is_admin"] == 1);
            
            if ($_SESSION["admin"]) {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="loginstyles.css">
</head>
<body>
    <div class="login-container">
        <div class="form-container">
            <h2>Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post" class="login-form">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="login-btn">Login</button>
            </form>
            
            <div class="form-footer">
                <p>Don\'t have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</body>
</html>';

    // Offer to replace login.php
    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='fix_login' value='1'>";
    echo "<button type='submit' style='padding: 10px; background: #007bff; color: white; border: none; cursor: pointer;'>Replace login.php with a Fixed Version</button>";
    echo "</form>";
    
    if (isset($_POST['fix_login'])) {
        // Make a backup first
        copy('login.php', 'login.php.backup');
        
        $write_result = file_put_contents('login.php', $new_login_content);
        if ($write_result !== false) {
            echo "<p style='color:green'>✓ login.php has been replaced (original backed up to login.php.backup)</p>";
        } else {
            echo "<p style='color:red'>✗ Failed to write new login.php</p>";
        }
    }
}

// Final instructions
echo "<h2>Final Step: Try Logging In</h2>";
echo "<p>Admin accounts have been verified and passwords reset. Try logging in with:</p>";
echo "<ul>";
echo "<li><strong>Email:</strong> admin@example.com</li>";
echo "<li><strong>Password:</strong> Admin123!</li>";
echo "</ul>";
echo "<p>Click here to <a href='login.php' style='color:blue;'>go to login page</a></p>";
echo "<p>If login still fails, the issue may be with sessions or redirection. Check the error logs or contact support.</p>";
?>
