<?php
include('includes/dbconnection.php');
header('Content-Type: application/json');

try {
    $stmt = $dbh->prepare("SELECT DISTINCT LOWER(tags) AS tag FROM tblqualification WHERE tags IS NOT NULL AND tags != ''");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tagList = [];
    foreach ($results as $row) {
        $tags = array_map('trim', explode(',', $row));
        foreach ($tags as $tag) {
            $tagList[] = strtolower($tag);
        }
    }

    // Return unique suggestions
    echo json_encode(array_values(array_unique($tagList)));
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
