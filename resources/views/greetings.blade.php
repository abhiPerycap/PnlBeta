@php

  Session::flush();

@endphp

<!DOCTYPE html>
<html lang="en" >
<head>
  <meta charset="UTF-8">
  <title>{{config('app.name')}}</title>
  <link rel="stylesheet" href="{{asset('css/style.css')}}">

</head>
<body onBlur="window.focus()">
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
    
    <!-- <form class="login-form"> -->
      <p>Hi, User Mapping was Successfull as you needed. Now click the below button to go back and click on the <b>Sync</b> Button. Thank you</p>
      <button id="close">Exit</button>
      <h6>This Window will automatically close in </h6><h6 id="msg" style="color:red;"></h6>
    <!-- </form> -->
  </div>
</div>
<!-- partial -->
  <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script><script  src="{{asset('js/script.js')}}"></script>
  <script>
   $(function(){//document.ready shortcut
     // setTimeout(function(){
     //  // alert('Action');
     //  window.close();
     // },3000);//timeout code to close window
    $("#close").click(function(){//target element and request click event
      window.close();
    });
   });
  </script>
  <script>
    // Set the date we're counting down to
    //var countDownDate = new Date().getTime();
    var countDownDate = new Date();
    countDownDate.setSeconds(countDownDate.getSeconds() + 7)

    // Update the count down every 1 second
    var x = setInterval(function() {

      // Get today's date and time
      var now = new Date().getTime();
        
      // Find the distance between now and the count down date
      var distance = countDownDate - now;
        
      // Time calculations for days, hours, minutes and seconds
      
      var seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
      // Output the result in an element with id="msg"
      document.getElementById("msg").innerHTML = seconds + "s ";
        
      // If the count down is over, write some text 
      if (distance < 0) {
        clearInterval(x);
        document.getElementById("msg").innerHTML = "EXPIRED";
        window.close();
      }
    }, 1000);
  </script>
</body>
</html>
