<?php

// MySQL database connection settings
$host = '';
$user = '';
$pass = '';
$db = '';

// Create a connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Backup filename
$date = date("Y-m-d-H");
$backup_file = "$db-$date.sql";
$delete_date = date("Y-m-d-H", strtotime('-7 days', strtotime($date)));
$delete_file = "$db-$delete_date.sql";

// MySQL backup command
$command = sprintf(
    'mysqldump --opt -h %s -u %s -p"%s" %s > /tmp/%s',
    escapeshellarg($host),
    escapeshellarg($user),
    escapeshellarg($pass),
    escapeshellarg($db),
    escapeshellarg($backup_file)
);

// Execute the command
system($command);

// Azure Storage Account details
$account_name = '';
$account_key = '';
$container_name = '';

// Upload the backup file to Azure Storage Account
require_once 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;

try {
    // Create blob client object
    $connectionString = "DefaultEndpointsProtocol=https;AccountName=$account_name;AccountKey=$account_key;EndpointSuffix=core.windows.net";
    $blobClient = BlobRestProxy::createBlobService($connectionString);

    // Set blob content type as plain text
    $createBlockBlobOptions = new CreateBlockBlobOptions();
    $createBlockBlobOptions->setContentType('text/plain');

    // Upload the backup file to Azure Storage Account
    $blobClient->createBlockBlob(
        $container_name,
        $backup_file,
        fopen("/tmp/$backup_file", 'r'),
        $createBlockBlobOptions
    );

    // Delete old backup file from Azure Storage
    $blobClient->deleteBlob($container_name, $delete_file);
} catch (ServiceException $e) {
    echo "ServiceException encountered: " . $e->getMessage();
}

// Delete the backup file from local storage
unlink("/tmp/$backup_file");

echo "Backup created successfully and uploaded to Azure Storage Account.";