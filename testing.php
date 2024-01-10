<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
$apiUrl = 'http://localhost/hazelmoneydb_api/users.php'; // Replace with your actual API endpoint

$options = array(
    'http' => array(
        'method' => 'GET',
        'header' => 'Content-Type: application/json' // Adjust headers based on your API requirements
    )
);

$context = stream_context_create($options);
$response = file_get_contents($apiUrl, false, $context);
$users = json_decode($response, true);

if ($response === FALSE) {
    die('Error fetching data from the API '. error_get_last()['message']);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <title>Users</title>
</head>
<body>
    <form id="userForm" class="col-sm-4 mx-auto">
        <label for="id" class="form-label">Id:</label>
        <input type="text" name="id" class="form-control center-block" placeholder="Introduza o id" required><br>
        <!-- <label for="email" class="form-label">Email:</label>
        <input type="email" name="email" class="form-control center-block" placeholder="Introduza o email" required><br>
        <label for="username" class="form-label">Username:</label>
        <input type="text" name="username" class="form-control center-block" placeholder="Introduza o username" required><br>
        <label for="password" class="form-label">Password:</label>
        <input type="password" name="password" class="form-control center-block" placeholder="Introduza a password" required><br> -->
        <div class="text-center">
            <button type="button" class="btn btn-primary" onclick="submitForm()">Submit</button>
        </div>
    </form>
    <script>
        function submitForm() {
            const form = document.getElementById('userForm');
            const formData = new FormData(form);

            const jsonData = {};
            formData.forEach((value, key) => {
                jsonData[key] = value;
            });

            // Convert formData to JSON
            const jsonString = JSON.stringify(jsonData);

            // Now you can send jsonString to the server using Fetch API or other methods
            fetch('http://localhost/hazelmoneydb_api/users.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: jsonString,
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
            })
            .catch(error => {
                console.error('Error submitting form:', error);
            });
        }
    </script>
</body>
</html>
