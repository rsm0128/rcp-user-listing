jQuery('document').ready(function ($) {
  $('.profile-search__field-service').select2({
    placeholder: "Select a Service",
    allowClear: true
  });

  $('.btn-near-me').click(function (e) {
    geoFindMe(sendCloseToMeRequest, (msg) => {
      alert(msg);
    });
  })

  const sendCloseToMeRequest = (latitude, longitude) => {
    $('#lat').val(latitude);
    $('#long').val(longitude);
    $('.profile-search__field-name').val('');
    $('.profile-search__field-service').val('');
    $('.profile-search__submit').click();
  }

  function geoFindMe(successCb, failedCb) {
    function success(position) {
      const latitude = position.coords.latitude
      const longitude = position.coords.longitude
      successCb(latitude, longitude)
    }

    function error(e) {
      console.log(e)
      failedCb('Unable to retrieve your location')
    }

    if (!navigator.geolocation) {
      failedCb('Geolocation is not supported by your browser')
    } else {
      navigator.geolocation.getCurrentPosition(success, error)
    }
  }

  // Reset the location 
  if ($('.profile-search__nearme').is(':checked')) {
    geoFindMe(sendCloseToMeRequest, (msg) => {
      alert(msg);
    });
  }
})