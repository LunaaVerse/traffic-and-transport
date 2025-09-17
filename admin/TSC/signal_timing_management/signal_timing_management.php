<?php
session_start();
// Access granted for all logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$host = "localhost:3307";
$user = "root";
$pass = "";
$db   = "tsc";
$conn = new mysqli($host,$user,$pass,$db);
if($conn->connect_error) die("DB Error: ".$conn->connect_error);

// ===== CRUD =====
if(isset($_POST['add'])){
    $intersection = $_POST['intersection'];
    $green_time = $_POST['green_time'];
    $yellow_time = $_POST['yellow_time'];
    $red_time = $_POST['red_time'];

    $sql="INSERT INTO signal_timings (intersection,green_time,yellow_time,red_time)
          VALUES ('$intersection','$green_time','$yellow_time','$red_time')";
    if($conn->query($sql)){
        echo "<script>Swal.fire('Added!','Signal timing added.','success').then(()=>window.location='signal_intersection_map.php');</script>";
    } else {
        echo "<script>Swal.fire('Error!','Failed to add.','error');</script>";
    }
}

if(isset($_POST['update'])){
    $id=$_POST['id'];
    $intersection=$_POST['intersection'];
    $green_time=$_POST['green_time'];
    $yellow_time=$_POST['yellow_time'];
    $red_time=$_POST['red_time'];

    $sql="UPDATE signal_timings SET intersection='$intersection',green_time='$green_time',
          yellow_time='$yellow_time',red_time='$red_time' WHERE id=$id";
    if($conn->query($sql)){
        echo "<script>Swal.fire('Updated!','Signal timing updated.','success').then(()=>window.location='signal_intersection_map.php');</script>";
    } else {
        echo "<script>Swal.fire('Error!','Failed to update.','error');</script>";
    }
}

if(isset($_GET['delete'])){
    $id=$_GET['delete'];
    $sql="DELETE FROM signal_timings WHERE id=$id";
    if($conn->query($sql)){
        echo "<script>Swal.fire('Deleted!','Signal timing deleted.','success').then(()=>window.location='signal_intersection_map.php');</script>";
    } else {
        echo "<script>Swal.fire('Error!','Failed to delete.','error');</script>";
    }
}

// Fetch timings
$timings=$conn->query("SELECT * FROM signal_timings ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Signal Intersection Map</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<style>
.intersection-card{width:150px; height:150px; margin:10px; padding:10px; text-align:center; border:1px solid #ccc; border-radius:8px;}
.signal{width:40px;height:40px;border-radius:50%;margin:5px auto; background-color:grey;}
.signal-label{font-weight:bold;}
.grid-container{display:flex; flex-wrap:wrap;}
</style>
</head>
<body class="p-4">

<div class="container">
<h2>Signal Timing Intersection Map (Admin)</h2>

<!-- Add/Edit Form -->
<div class="card mb-3 p-3">
<form method="POST" id="timingForm">
<input type="hidden" name="id" id="timingId">
<div class="row mb-2">
<div class="col"><input type="text" name="intersection" id="intersection" class="form-control" placeholder="Intersection Name" required></div>
<div class="col"><input type="number" name="green_time" id="green_time" class="form-control" placeholder="Green (sec)" required></div>
<div class="col"><input type="number" name="yellow_time" id="yellow_time" class="form-control" placeholder="Yellow (sec)" required></div>
<div class="col"><input type="number" name="red_time" id="red_time" class="form-control" placeholder="Red (sec)" required></div>
</div>
<button type="submit" name="add" id="addBtn" class="btn btn-success">Add</button>
<button type="submit" name="update" id="updateBtn" class="btn btn-primary d-none">Update</button>
<button type="button" id="cancelBtn" class="btn btn-secondary d-none">Cancel</button>
</form>
</div>

<!-- Intersection Map -->
<div class="grid-container" id="intersectionMap">
<?php
$timings2=$conn->query("SELECT * FROM signal_timings");
while($t=$timings2->fetch_assoc()):
?>
<div class="intersection-card" id="card_<?=$t['id']?>">
<div class="signal red" id="red_<?=$t['id']?>"></div>
<div class="signal yellow" id="yellow_<?=$t['id']?>"></div>
<div class="signal green" id="green_<?=$t['id']?>"></div>
<div class="signal-label"><?=$t['intersection']?></div>
<a href="?delete=<?=$t['id']?>" class="btn btn-danger btn-sm mt-1" onclick="return confirm('Delete this intersection?')">Delete</a>
<button class="btn btn-primary btn-sm mt-1 editBtn"
data-id="<?=$t['id']?>"
data-intersection="<?=$t['intersection']?>"
data-green="<?=$t['green_time']?>"
data-yellow="<?=$t['yellow_time']?>"
data-red="<?=$t['red_time']?>">Edit</button>
</div>
<?php endwhile; ?>
</div>

</div>

<script>
$(document).ready(function(){
    $('.editBtn').click(function(){
        let id=$(this).data('id');
        $('#timingId').val(id);
        $('#intersection').val($(this).data('intersection'));
        $('#green_time').val($(this).data('green'));
        $('#yellow_time').val($(this).data('yellow'));
        $('#red_time').val($(this).data('red'));
        $('#addBtn').addClass('d-none'); $('#updateBtn,#cancelBtn').removeClass('d-none');
    });
    $('#cancelBtn').click(function(){
        $('#timingForm')[0].reset();
        $('#addBtn').removeClass('d-none'); $('#updateBtn,#cancelBtn').addClass('d-none');
    });

    // ===== Live Simulation =====
    let signals=[];
    <?php
    $timings3=$conn->query("SELECT * FROM signal_timings");
    while($t3=$timings3->fetch_assoc()){
        echo "signals.push({id:".$t3['id'].",green:".$t3['green_time'].",yellow:".$t3['yellow_time'].",red:".$t3['red_time'].",state:'red'});\n";
    }
    ?>

    function updateSignals(){
        signals.forEach(sig=>{
            let r=$('#red_'+sig.id), y=$('#yellow_'+sig.id), g=$('#green_'+sig.id);
            if(!sig.counter) sig.counter=sig.red;
            if(sig.state=='red'){
                r.css('background-color','red'); y.css('background-color','grey'); g.css('background-color','grey');
                sig.counter--;
                if(sig.counter<=0){sig.state='green'; sig.counter=sig.green;}
            } else if(sig.state=='green'){
                r.css('background-color','grey'); y.css('background-color','grey'); g.css('background-color','green');
                sig.counter--;
                if(sig.counter<=0){sig.state='yellow'; sig.counter=sig.yellow;}
            } else if(sig.state=='yellow'){
                r.css('background-color','grey'); y.css('background-color','yellow'); g.css('background-color','grey');
                sig.counter--;
                if(sig.counter<=0){sig.state='red'; sig.counter=sig.red;}
            }
        });
    }

    setInterval(updateSignals,1000);
});
</script>

</body>
</html>
