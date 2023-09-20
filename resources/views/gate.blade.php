<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8">
  <title>{{config('app.name')}}</title>
  <link rel="stylesheet" href="{{asset('css/style.css')}}">

</head>
<body>
<!-- partial:index.partial.html -->
<div class="login-page">
  @if (\Session::has('error'))
    <div class="alert alert-error">
        <ul>
            <li>{!! \Session::get('error') !!}</li>
        </ul>
    </div>
@endif
  <div class="form">
    
    <form class="login-form" method="post">
      @csrf
      <p>Hello <b>{{$user['memberId']}}</b>, We Just Need to Verify that its really you</p>
      <input type="hidden" name="_id" value="{{$user['_id']}}"/>
      <input type="hidden" name="memberId" value="{{$user['memberId']}}"/>
      <input type="password" name="password" placeholder="password" required/>
      <button>login</button>
    </form>
  </div>
</div>
<!-- partial -->
  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script><script  src="{{asset('js/script.js')}}"></script>
  <script type="text/javascript">
    // onblur = function() {
setTimeout('self.focus()',100);
// }
  </script>
</body>
</html>
