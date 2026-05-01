<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QSU-OVS</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- AOS CSS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
    
    .hero-section {
      position: relative;
      text-align: center;
      padding: 60px 25px;
      background-color: #198754; /* Bootstrap success green */
      color: white;
    }

    .hero-section::before {
      content: "";
      background: url("Qsu logo.png") no-repeat center;
      background-size: 300px; /* adjust logo size */
      opacity: 0.60; /* faded effect */
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 60px;
      z-index: 0;
    }

    .hero-section h1,
    .hero-section h2,
    .hero-section h5 {
      position: relative;
      z-index: 1; /* bring text above the logo */
    }
    .btn {
      position: relative;
      z-index: 10;
    }

    .fade-in-left {
      opacity: 0;
      transform: translateX(-50px);
      animation: fadeInLeft 1s ease forwards;
    }
    @keyframes fadeInLeft {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    /* Fade In from Right */
    .fade-in-right {
      opacity: 0;
      transform: translateX(50px);
      animation: fadeInRight 1s ease forwards;
      animation-delay: 0.3s;
    }
    @keyframes fadeInRight {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

  </style>
</head>


<body>

  <nav class="navbar navbar-expand-lg navbar-dark bg-success px-5">
    <div class="container-fluid mx-5">
      <a class="navbar-brand" href="#">
        <img src="Qsu logo.png" alt="QSU Logo" width="40" height="40" class="d-inline-block align-text-center">
        QSU-OVS
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="landingPage.php">Home</a>
          </li>
          <li class="nav-item d-flex align-items-center">
            <span class="mx-2 text-white">|</span>
            <a class="nav-link" href="/login
            ">Log in</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <!-- Hero Section -->
  <div class="container-fluid hero-section my-0 text-center" data-aos="fade-up">
    <h2 class="" data-aos="fade-down" data-aos-delay="200">Student Government<br></h2>
    <h1 class="fw-bolder mb-3" style="font-size: 5rem; line-height: 1.1;" data-aos="zoom-in" data-aos-delay="400">
      QSU-FMAS <br> 
    </h1>
    <h5 class="mt-2 fw-normal" data-aos="fade-up" data-aos-delay="600">
      <small>File Management and Archiving System</small>
    </h5>

    <a href="/login" 
       class="btn fw-bold mt-3 py-2 px-4 text-white" 
       style="background-color: #FF9B00; border-radius: 10px;"
       data-aos="flip-up" data-aos-delay="800">
      Get Started
    </a>
  </div>


  <!-- Info Section -->
  <div class="infoSec text-success">
    <div class="container my-5">
      <div class="row align-items-center" style="min-height: 200px;">
        <!-- Left Column -->
        <div class="col-md-6 d-flex flex-column justify-content-center text-start" data-aos="fade-right">
          <h1 class="fw-bold">Vote with Confidence</h1>
          <p>We ensure that your vote remains secure and anonymous throughout the election process.</p>
        </div>

        <!-- Right Column -->
        <div class="col-md-6 d-flex flex-column justify-content-center text-end" data-aos="fade-left">
          <h1 class="fw-bold">Your Vote Matters!</h1>
          <p>Every vote counts in making a difference. Shape the future of Quirino State University with your vote.</p>
        </div>
      </div>
    </div>
  </div>


 <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<footer class="bg-dark text-white text-center py-4">
  <div class="container">
    <h5 class="mb-3 fw-bold">Quirino State University-Online Voting System</h5>
    <h6 class="mb-2">© 2025.All Rights Reserved. QSU-CITCS</h6>
  </div>
</footer>

<!-- AOS JS -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({
    duration: 1200, // animation speed in ms
    once: true      // animation runs only once
  });
</script>


<!-- AOS Animation Library -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<script>
  AOS.init({
    duration: 800,
    once: true
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>
