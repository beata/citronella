(function(window){

  window.__ = function(string) {
    return (Site && Site.messages && Site.messages[string]) || string;
  };

}(window));
