<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Custom Bootstrap Starter Template">
    <meta name="author" content="Your Name">
    <link rel="icon" href="/path-to-your-favicon.ico">

    <title>Custom Bootstrap Template</title>

    <!-- Bootstrap core CSS -->
    <link href="<?= base_url('bootstrap-4.0.0/dist/css/bootstrap.min.css') ?>" rel="stylesheet">

    <!-- Custom styles -->
    <style>
        body {
            background: linear-gradient(45deg, #1e90ff, #ff6f61);
            color: white;
            padding-top: 5rem;
        }
        .starter-template {
            padding: 3rem 1.5rem;
            text-align: center;
            color: #f8f9fa;
        }
        .navbar {
            background-color: #4b0082;
        }
        .navbar-brand, .nav-link {
            color: #f8f9fa !important;
        }
        .nav-link:hover {
            color: #ff6f61 !important;
        }
        .form-control {
            border-radius: 20px;
        }
        .btn-outline-success {
            color: #f8f9fa;
            border-color: #f8f9fa;
        }
        .btn-outline-success:hover {
            background-color: #ff6f61;
            border-color: #ff6f61;
        }
    </style>
  </head>

  <body>

    <?= $this->include('navbar') ?>

    <main role="main" class="container">
      <?= $this->renderSection('content') ?>
    </main>

    <script src="<?= base_url('jquery-3.7.1.js') ?>"></script>
    <script src="<?= base_url('bootstrap-4.0.0/dist/js/bootstrap.min.js') ?>"></script>
  </body>
</html>
