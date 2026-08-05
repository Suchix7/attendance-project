<section class="header">
    <div class="logo">
        <i class="ri-menu-line icon icon-0 menu"></i>
        <h2>CMS <span>Portal</span></h2>
    </div>
    <div class="search--notification--profile">
        <div class="search">
            <input type="text" id="searchText" placeholder="Search tables..." oninput="searchItems()">
            <button><i class="ri-search-line"></i></button>
        </div>
        <div class="notification--profile">
            <div class="picon lock">
                <i class="ri-user-3-line" style="margin-right: 5px;"></i> <?php echo user()->name ?>
            </div>

            <div class="picon profile">
                <img src="resources/images/user.png" alt="">
            </div>
        </div>
    </div>
</section>
<script>
    function searchItems() {
        var input = document.getElementById('searchText').value.toLowerCase();
        var rows = document.querySelectorAll('table tr');

        rows.forEach(function (row) {
            var cells = row.querySelectorAll('td');
            var found = false;

            cells.forEach(function (cell) {
                if (cell.innerText.toLowerCase().includes(input)) {
                    found = true;
                }
            });

            if (found) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>