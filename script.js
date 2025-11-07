// Profile Dropdown and Logout Modal Functionality
document.addEventListener('DOMContentLoaded', function() {
  // Profile Dropdown Toggle
  var profileDropdownBtn = document.getElementById('profileDropdownBtn');
  var profileDropdown = document.getElementById('profileDropdown');
  var dropdownArrow = document.getElementById('dropdownArrow');
  
  if (profileDropdownBtn && profileDropdown) {
    profileDropdownBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      profileDropdown.classList.toggle('hidden');
      dropdownArrow.classList.toggle('rotate-180');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (!profileDropdownBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
        profileDropdown.classList.add('hidden');
        dropdownArrow.classList.remove('rotate-180');
      }
    });
  }
  
  // Logout Modal Functionality
  var dropdownLogoutBtn = document.getElementById('dropdownLogoutBtn');
  var logoutModal = document.getElementById('logoutModal');
  var cancelLogout = document.getElementById('cancelLogout');
  var confirmLogout = document.getElementById('confirmLogout');

  if (dropdownLogoutBtn && logoutModal) {
    dropdownLogoutBtn.addEventListener('click', function(e) {
      e.preventDefault();
      logoutModal.classList.remove('hidden');
      profileDropdown.classList.add('hidden'); // Close dropdown
    });
  }

  if (cancelLogout && logoutModal) {
    cancelLogout.addEventListener('click', function() {
      logoutModal.classList.add('hidden');
    });
  }

  if (confirmLogout && logoutModal) {
    confirmLogout.addEventListener('click', function() {
      logoutModal.classList.add('hidden');
      window.location.href = 'login.html';
    });
  }
});