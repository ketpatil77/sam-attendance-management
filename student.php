<?php
session_start();
if (!isset($_SESSION['admin_user'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body class="p-4 bg-light">
<div class="d-flex justify-content-end mb-3">
    <form action="logout.php" method="post">
        <button type="submit" class="btn btn-outline-danger">Logout</button>
    </form>
</div>
<div class="container">
    <h3 class="mb-4">Staff Registration</h3>

    <form id="staffForm" enctype="multipart/form-data">
        <input type="hidden" id="eid" name="eid">
        <input type="hidden" id="photo64" name="photo">
        <div class="row g-3 mb-2">
            <div class="col-md-4"><input required class="form-control" id="name" name="name" placeholder="Name"></div>
            <div class="col-md-4"><input class="form-control" id="mobile" name="mobile" placeholder="Mobile"></div>
            <div class="col-md-4"><input class="form-control" id="designation" name="designation" placeholder="Designation"></div>
            <div class="col-md-4">
                <select class="form-select" id="dept" name="dept">
                    <option value="">Department</option><option>HR</option><option>Admin</option>
                    <option>IT</option><option>Finance</option><option>Teaching</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="file" class="form-control" id="photoInput" accept="image/*">
            </div>
            <div class="col-md-6">
                <img id="preview" src="" alt="Preview" style="max-height: 80px;">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Save</button>
        <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
    </form>

    <hr>

    <table class="table table-bordered" id="staffTable">
        <thead class="table-dark">
        <tr>
            <th>Photo</th><th>Name</th><th>Mobile</th><th>Designation</th><th>Dept</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<script>
function loadStaff() {
    $.post('staff_api.php', { action: 'read' }, function(data) {
        const staff = JSON.parse(data);
        let html = '';
        staff.forEach(row => {
            html += `<tr>
                <td><img src="${row.PHOTO}" style="height:50px;"></td>
                <td>${row.NAME}</td>
                <td>${row.MOBILE}</td>
                <td>${row.DESIGNATION}</td>
                <td>${row.DEPARTMENT}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick='editStaff(${JSON.stringify(row)})'>Edit</button>
                    <button class="btn btn-sm btn-danger" onclick='deleteStaff(${row.EID})'>Delete</button>
                </td>
            </tr>`;
        });
        $('#staffTable tbody').html(html);
    });
}

function resetForm() {
    $('#staffForm')[0].reset();
    $('#eid').val('');
    $('#preview').attr('src', '');
    $('#photo64').val('');
}

$('#photoInput').on('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(evt) {
        const base64 = evt.target.result.split(',')[1];
        $('#preview').attr('src', evt.target.result);
        $('#photo64').val(base64);
    };
    reader.readAsDataURL(file);
});

$('#staffForm').submit(function(e) {
    e.preventDefault();
    const formData = $(this).serialize();
    const action = $('#eid').val() ? 'update' : 'create';
    $.post('staff_api.php', formData + '&action=' + action, function(res) {
        alert(res);
        resetForm();
        loadStaff();
    });
});

function editStaff(s) {
    $('#eid').val(s.EID);
    $('#name').val(s.NAME);
    $('#mobile').val(s.MOBILE);
    $('#designation').val(s.DESIGNATION);
    $('#dept').val(s.DEPARTMENT);
    if (s.PHOTO) {
        $('#preview').attr('src', s.PHOTO);
    }
}

function deleteStaff(eid) {
    if (confirm("Are you sure?")) {
        $.post('staff_api.php', { action: 'delete', eid }, function(res) {
            alert(res);
            loadStaff();
        });
    }
}

$(document).ready(loadStaff);
</script>

</body>
</html>










