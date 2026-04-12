//check for the active page and style the link

let menu = document.querySelector(".menu");
let sidebar = document.querySelector(".sidebar");
let mainContent = document.querySelector(".main--content");
let logo = document.querySelector(".logo");
let searchProfile = document.querySelector(".search--notification--profile");

if (menu) {
  menu.onclick = function () {
    if (sidebar) sidebar.classList.toggle("active");
    if (mainContent) mainContent.classList.toggle("active");
    if (logo) logo.classList.toggle("active");
    if (searchProfile) searchProfile.classList.toggle("active");
  };
}
document.addEventListener("DOMContentLoaded", function () {
  var currentUrl = window.location.href;
  var links = document.querySelectorAll(".sidebar a");
  links.forEach(function (link) {
    if (link.href === currentUrl) {
      link.classList.add("active");
    }
  });
});
