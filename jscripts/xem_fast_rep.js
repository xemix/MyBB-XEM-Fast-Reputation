function vote(uid, pid, reputation) {
  $.ajax({
    type: "POST",
    url: "xmlhttp.php",
    data: {
      "action": "xem_fast_rep",
      "uid": uid,
      "pid": pid,
      "reputation": reputation
    }, 
    cache: false,

    success: function(data){
      $(".reps_"+pid).html(data);
    },
    fail: function() {
        alert("Problem z wtyczką XEM Fast Rep");
    }
  });
}