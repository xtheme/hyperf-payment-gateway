<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
<form method="POST" id="form" action="{{$create_url}}" enctype="application/x-www-form-urlencoded">
    @foreach ($form as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
</form>

<script type="text/javascript">
  document.getElementById('form').submit();
</script>
</body>
</html>