// ✅ profileDropdown.js
(function () {
  // --- Toggle dropdown visibility ---
  window.toggleProfileDropdown = function () {
    const dropdown = document.getElementById("profileDropdown");
    const arrow = document.getElementById("profileArrow");
    if (!dropdown) return;

    const isHidden = dropdown.classList.contains("hidden");
    dropdown.classList.toggle("hidden", !isHidden);
    if (arrow) arrow.style.transform = isHidden ? "rotate(180deg)" : "rotate(0deg)";
  };

  // --- Close dropdown when clicking outside ---
  document.addEventListener("click", function (event) {
    const dropdown = document.getElementById("profileDropdown");
    const profileButton = event.target.closest?.('button[onclick="toggleProfileDropdown()"]');
    if (!profileButton && dropdown && !dropdown.contains(event.target)) {
      dropdown.classList.add("hidden");
      const arrow = document.getElementById("profileArrow");
      if (arrow) arrow.style.transform = "rotate(0deg)";
    }
  });

  // --- “My Profile” link (placeholder for future use) ---
  window.openProfileModal = function () {
    const dd = document.getElementById("profileDropdown");
    if (dd) dd.classList.add("hidden");
    const arrow = document.getElementById("profileArrow");
    if (arrow) arrow.style.transform = "rotate(0deg)";
    alert("Open My Profile modal or page here.");
  };

  // --- Initialize user info from localStorage ---
  function initProfile() {
    try {
      const storedName = localStorage.getItem("profileName") || "Admin User";
      const storedEmail = localStorage.getItem("profileEmail") || "admin@jtmk.com";
      const role = localStorage.getItem("userRole") || "user";

      const profileName = document.getElementById("profileName");
      const initials = document.getElementById("profileInitials");
      const ddInitials = document.getElementById("profileDropdownInitials");
      const ddName = document.getElementById("profileDropdownName");
      const ddEmail = document.getElementById("profileDropdownEmail");
      const roleBadge = document.getElementById("roleBadge");

      // Update top navbar
      if (profileName) profileName.textContent = storedName;
      if (initials) initials.textContent = storedName.split(" ").map(n => n[0]).join("").toUpperCase();

      // Update dropdown content
      if (ddInitials) ddInitials.textContent = initials ? initials.textContent : "";
      if (ddName) ddName.textContent = storedName;
      if (ddEmail) ddEmail.textContent = storedEmail;

      // Role badge
      if (roleBadge) {
        roleBadge.textContent = role === "admin" ? "Admin" : "";
      }
    } catch (e) {
      console.warn("initProfile error", e);
    }
  }

  document.addEventListener("DOMContentLoaded", initProfile);
})();
