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

$backup_file = $db . '-' . $date . '.sql';
$delete_date = date("Y-m-d-H", strtotime('-7 day', strtotime($date)));
$delete_file = $db . '-' . $delete_date . '.sql';
// MySQL backup command
$command = "mysqldump --opt -h $host -u $user -p'$pass' $db > /tmp/$backup_file";

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
use MicrosoftAzure\Storage\Blob\Models\Blob;
use MicrosoftAzure\Storage\Blob\Models\Block;
use MicrosoftAzure\Storage\Blob\Models\BlockList;
use MicrosoftAzure\Storage\Blob\Models\CommitBlobBlocksOptions;
use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;

try {
    // Create blob client object
    $connectionString = "DefaultEndpointsProtocol=https;AccountName=$account_name;AccountKey=$account_key;EndpointSuffix=core.windows.net";
    $blobClient = BlobRestProxy::createBlobService($connectionString);

    // Create blob options object
    $createBlockBlobOptions = new CreateBlockBlobOptions();

    // Set blob content type as plain text
    $createBlockBlobOptions->setContentType('text/plain');

    // Upload the backup file to Azure Storage Account
    $blobClient->createBlockBlob(
        $container_name,
        $backup_file,
        fopen('/tmp/' . $backup_file, 'r'),
        $createBlockBlobOptions
    );
    $blobClient->deleteBlob($container_name, $delete_file);
} catch (ServiceException $e) {
    echo "ServiceException encountered: " . $e->getMessage();
} catch (InvalidArgumentTypeException $e) {
    echo "InvalidArgumentTypeException encountered: " . $e->getMessage();
}

// Delete the backup file from local storage
unlink('/tmp/' . $backup_file);

echo "Backup created successfully and uploaded to Azure Storage Account.";
