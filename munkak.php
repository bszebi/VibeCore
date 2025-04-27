<?php

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        // Először keressük meg a kapcsolódó értesítéseket
        $find_notifications = "SELECT id FROM notifications WHERE work_id = $id";
        $notifications_result = $conn->query($find_notifications);
        
        if ($notifications_result && $notifications_result->num_rows > 0) {
            // Ha vannak kapcsolódó értesítések, töröljük őket egyenként
            while ($notification = $notifications_result->fetch_assoc()) {
                $notification_id = $notification['id'];
                $delete_notification = "DELETE FROM notifications WHERE id = $notification_id";
                $conn->query($delete_notification);
            }
        }
        
        // Most, hogy az értesítések törölve vannak, törölhetjük a munkát
        $delete_work = "DELETE FROM work WHERE id = $id";
        if ($conn->query($delete_work)) {
            echo "<script>alert('Sikeres törlés!'); window.location.href='munkak.php';</script>";
        } else {
            throw new Exception("Nem sikerült törölni a munkát: " . $conn->error);
        }
        
    } catch (Exception $e) {
        echo "Hiba történt a törlés során: " . $e->getMessage();
    }
} 