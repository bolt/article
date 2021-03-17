(function() {
    // Bind Click event to the drop down navigation button
    document.querySelectorAll('.nav-button').forEach(function(navButton) {
        navButton.addEventListener('click', function() {
            var currentSection = this.parentNode.parentNode;
            // Autorise only one draggable section to open up at a time
            document.querySelectorAll('.drop-down').forEach(function(dropDown) {
                if (dropDown.classList.contains('closed') == false && dropDown != currentSection) {
                    dropDown.classList.toggle('overflow-hidden');
                    dropDown.classList.toggle('closed');
                }
            }, null, currentSection);

            var active = this.parentNode.parentNode
            if (active.classList.contains('closed')) {
                setTimeout(function() { 
                    active.classList.toggle('closed');
                    setTimeout(function(){ 
                        active.classList.toggle('overflow-hidden');
                    }, 500, active);
                }, 500, active);
            }
        
        });
    });
})();