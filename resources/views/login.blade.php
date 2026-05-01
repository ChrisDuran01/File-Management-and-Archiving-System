
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
<section class="vh-100 gradient-custom">
  <div class="container h-100">
    <div class="row d-flex justify-content-center align-items-center h-100">
      <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card bg-success text-white" style="border-radius: 1rem;">
          <div class="card-body p-5 text-center">

            <div class="mb-md-5 mt-md-4">
              <form method="POST" action="/login">
@csrf

<h2 class="fw-bold mb-2 text-uppercase">Login</h2>
<p class="text-white-50 mb-5">Please enter your email and password!</p>

<div class="form-outline form-white mb-4">
    <input type="email" name="email" id="typeEmailX" class="form-control form-control-lg" required />
    <label class="form-label" for="typeEmailX">Email</label>
</div>

<div class="form-outline form-white mb-4">
    <input type="password" name="password" id="typePasswordX" class="form-control form-control-lg" required />
    <label class="form-label" for="typePasswordX">Password</label>
</div>

<p class="small mb-5 pb-lg-2">
    <a class="text-white-50" href="#">Forgot password?</a>
</p>

<button class="btn btn-outline-light btn-lg px-5" type="submit">Login</button>

</form>

          </div>
        </div>
      </div>
    </div>
  </div>
</section>
