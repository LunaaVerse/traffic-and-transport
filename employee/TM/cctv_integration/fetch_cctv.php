<?php
$conn = new mysqli("localhost:3307", "root", "", "chat");
$result = $conn->query("SELECT * FROM cctv_feeds ORDER BY created_at DESC");

$feeds = [];
$table = "<table><tr><th>ID</th><th>Location</th><th>Feed</th><th>Status</th><th>Actions</th></tr>";

while ($row = $result->fetch_assoc()) {
    $feeds[] = $row;

    // log alert kapag inactive
    if ($row['status'] === 'Inactive') {
        $check = $conn->query("SELECT * FROM cctv_alerts WHERE cctv_id={$row['cctv_id']} ORDER BY alert_time DESC LIMIT 1");
        if ($check->num_rows === 0 || strtotime($check->fetch_assoc()['alert_time']) < time() - 60) {
            $msg = "CCTV '{$row['location']}' (ID {$row['cctv_id']}) has gone INACTIVE.";
            $conn->query("INSERT INTO cctv_alerts (cctv_id, alert_message) VALUES ({$row['cctv_id']}, '$msg')");
        }
    }

    $table .= "<tr>
                <td>{$row['cctv_id']}</td>
                <td>{$row['location']}</td>
                <td>";
    if ($row['status'] == 'Active') {
        $table .= "<iframe src='{$row['stream_url']}'></iframe>";
    } else {
        $table .= "🔴 Inactive";
    }
    $table .= "</td>
               <td>{$row['status']}</td>
               <td>
                  <a href='toggle_cctv.php?id={$row['cctv_id']}'>Toggle</a> | 
                  <a href='delete_cctv.php?id={$row['cctv_id']}'>Delete</a>
               </td>
              </tr>";
}
$table .= "</table>";

echo json_encode(["feeds" => $feeds, "table" => $table]);
$conn->close();
?>
