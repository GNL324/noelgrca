<?php
/**
 * GNL324 Contact Form Handler
 * Stores submissions in DB + emails a copy to 1@noelgrca.com
 */

error_reporting(0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Load DB config
$db_config = [
    'host'     => 'localhost',
    'database' => 'u532675227_LifeOS',
    'username' => 'u532675227_LOS',
    'password' => 'Clave0225!',
    'charset'  => 'utf8mb4'
];

// Email settings
define('TO_EMAIL', '1@noelgrca.com');
define('FROM_EMAIL', 'noreply@noelgarca.com');
define('FROM_NAME', 'GNL324 Contact Form');

function get_db_connection() {
    global $db_config;
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['database']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function sanitize($str) {
    if ($str === null) return '';
    $str = trim($str);
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    return $str;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// --- Parse JSON body ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    // Fallback to form data
    $data = [
        'name'         => $_POST['name'] ?? '',
        'email'        => $_POST['email'] ?? '',
        'project_type' => $_POST['project_type'] ?? '',
        'budget'       => $_POST['budget'] ?? '',
        'message'      => $_POST['message'] ?? '',
    ];
}

// Validate required fields
$name         = sanitize($data['name'] ?? '');
$email        = sanitize($data['email'] ?? '');
$project_type = sanitize($data['project_type'] ?? '');
$budget       = sanitize($data['budget'] ?? '');
$message      = sanitize($data['message'] ?? '');

$errors = [];

if (empty($name))    $errors[] = 'Name is required';
if (empty($email))   $errors[] = 'Email is required';
if (empty($message)) $errors[] = 'Message is required';
if (!validate_email($email)) $errors[] = 'Invalid email address';
if (empty($project_type)) $errors[] = 'Project type is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    exit;
}

// --- Store in database ---
$pdo = get_db_connection();

if ($pdo) {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_submissions (
            id           VARCHAR(36) PRIMARY KEY,
            name         VARCHAR(255) NOT NULL,
            email        VARCHAR(255) NOT NULL,
            project_type VARCHAR(100) NOT NULL,
            budget       VARCHAR(100) DEFAULT '',
            message      TEXT NOT NULL,
            ip_address   VARCHAR(45) DEFAULT NULL,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at      DATETIME DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("
        INSERT INTO contact_submissions (id, name, email, project_type, budget, message, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $id = generate_uuid();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt->execute([$id, $name, $email, $project_type, $budget, $message, $ip]);
} else {
    // DB not available — continue anyway, email is the priority
}

// --- Send email ---
$project_type_labels = [
    'webapp'   => 'Web Application',
    'mobile'   => 'Mobile App',
    'website'  => 'Website',
    'software' => 'Custom Software',
    'other'    => 'Other / Not Sure',
];

$budget_labels = [
    'under-5k'  => 'Under $5,000',
    '5k-15k'    => '$5,000 – $15,000',
    '15k-50k'  => '$15,000 – $50,000',
    '50k-plus' => '$50,000+',
    'not-sure' => 'Not sure yet',
];

$label_pt  = $project_type_labels[$project_type] ?? $project_type;
$label_bud = $budget_labels[$budget] ?? ($budget ?: 'Not specified');

$email_subject = "[GNL324] New Project Inquiry from {$name}";
$email_body = "New contact form submission\n".
    "==============================\n\n".
    "Name:         {$name}\n".
    "Email:        {$email}\n".
    "Project Type: {$label_pt}\n".
    "Budget:       {$label_bud}\n\n".
    "Message:\n".
    str_repeat('-', 40) . "\n".
    "{$message}\n\n".
    "==============================\n".
    "Submitted: " . date('Y-m-d H:i:s T') . "\n" .
    "IP: {$ip}";

// Try mail() first (Hostinger typically supports this)
$mail_sent = @mail(
    TO_EMAIL,
    $email_subject,
    $email_body,
    [
        'From'    => FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To' => "{$name} <{$email}>",
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]
);

// Also try direct SMTP via fsockopen if mail() fails
if (!$mail_sent) {
    // Try sending via SMTP directly (Hostinger mail relay)
    $smtp_result = send_smtp_email(TO_EMAIL, $email_subject, $email_body, $email, $name);
    $mail_sent = $smtp_result;
}

// --- Response ---
echo json_encode([
    'success'   => true,
    'message'   => 'Contact form submitted successfully',
    'id'        => $id ?? null,
    'mail_sent' => $mail_sent,
]);

// ===== SMTP fallback =====
function send_smtp_email($to, $subject, $body, $reply_to_email, $reply_to_name) {
    $smtp_host = 'localhost';
    $smtp_port = 25;
    $smtp_user = FROM_EMAIL;
    $smtp_pass = '';  // Use default sendmail on Hostinger
    $timeout = 5;

    try {
        $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, $timeout);
        if (!$socket) return false;

        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') return false;

        fputs($socket, "HELO localhost\r\n");
        fgets($socket, 512);

        if ($smtp_user && $smtp_pass) {
            fputs($socket, "AUTH LOGIN\r\n");
            fgets($socket, 512);
            fputs($socket, base64_encode($smtp_user) . "\r\n");
            fgets($socket, 512);
            fputs($socket, base64_encode($smtp_pass) . "\r\n");
            fgets($socket, 512);
        }

        fputs($socket, "MAIL FROM: <" . FROM_EMAIL . ">\r\n");
        fgets($socket, 512);
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        fgets($socket, 512);

        fputs($socket, "DATA\r\n");
        fgets($socket, 512);

        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: {$reply_to_name} <{$reply_to_email}>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        fputs($socket, "Subject: {$subject}\r\n{$headers}\r\n\r\n{$body}\r\n.\r\n");
        fgets($socket, 512);

        fputs($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
