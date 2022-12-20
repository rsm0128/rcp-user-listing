jQuery('document').ready(function ($) {
  $('.profile-search__nearme').change( 'change', function(e){
    if (this.checked) {
      geoFindMe(sendCloseToMeRequest, (msg) => {
        alert(msg);
      });
      // sendCloseToMeRequest('61.52401', '105.318756')
    }
  })

  const sendCloseToMeRequest = (latitude, longitude) => {
    $('#lat').val(latitude);
    $('#long').val(longitude);
    // $('.profile-search__submit').click();
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