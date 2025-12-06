<?php global $assetBase; ?>
    </div>
  </div>
</div>

<!-- [Footer Scripts] -->
<script src="<?= $assetBase ?>/assets/js/plugins/simplebar.min.js"></script>
<script src="<?= $assetBase ?>/assets/js/plugins/popper.min.js"></script>
<script src="<?= $assetBase ?>/assets/js/icon/custom-icon.js"></script>
<script src="<?= $assetBase ?>/assets/js/plugins/feather.min.js"></script>
<script src="<?= $assetBase ?>/assets/js/component.js"></script>
<script src="<?= $assetBase ?>/assets/js/theme.js"></script>
<script src="<?= $assetBase ?>/assets/js/script.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function() {
    const mobileToggle = document.getElementById("mobile-collapse");
    if (mobileToggle) {
      mobileToggle.addEventListener("click", function(e) {
        e.preventDefault();
        document.body.classList.toggle("pc-sidebar-open"); // this matches Datta Able’s class
      });
    }

    const sidebarToggle = document.getElementById("sidebar-hide");
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", function(e) {
        e.preventDefault();
        document.body.classList.toggle("pc-sidebar-collapsed");
      });
    }
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    if (window.feather) feather.replace();
  });
</script>
<script>
  // Ensure loader always hides after window load
  window.addEventListener('load', function() {
    const loader = document.querySelector('.loader-bg');
    if (loader) {
      loader.style.opacity = '0';
      loader.style.transition = 'opacity 0.5s ease';
      setTimeout(() => loader.remove(), 600);
    }
  });
</script>

<script>
// Prevent the Datta Able script from replacing PNG logos with SVGs
document.addEventListener("DOMContentLoaded", () => {
  // Mark all no-auto logos
  document.querySelectorAll('.no-auto-logo').forEach(el => {
    el.dataset.noAuto = true;
  });

  // Revert any logos that were forcibly changed to .svg
  document.querySelectorAll('.logo').forEach(logo => {
    if (logo.dataset.noAuto) return;
    const src = logo.getAttribute('src');
    if (src.endsWith('.svg')) {
      logo.setAttribute('src', src.replace('.svg', '.png'));
    }
  });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const scrollContainers = document.querySelectorAll(".table-responsive");

  scrollContainers.forEach(container => {
    let isDown = false;
    let startX;
    let scrollLeft;
    let isScrollable = false;

    // Check if table has horizontal overflow
    function updateScrollable() {
      isScrollable = container.scrollWidth > container.clientWidth + 10; // buffer
    }

    // Run check initially and on window resize
    updateScrollable();
    window.addEventListener("resize", updateScrollable);

    container.addEventListener("mousedown", e => {
      if (!isScrollable) return; // Only allow drag if scrollable
      isDown = true;
      container.classList.add("dragging");
      startX = e.pageX - container.offsetLeft;
      scrollLeft = container.scrollLeft;
      container.style.cursor = "grabbing";
    });

    container.addEventListener("mouseleave", () => {
      isDown = false;
      container.classList.remove("dragging");
      container.style.cursor = "grab";
    });

    container.addEventListener("mouseup", () => {
      isDown = false;
      container.classList.remove("dragging");
      container.style.cursor = "grab";
    });

    container.addEventListener("mousemove", e => {
      if (!isDown || !isScrollable) return;
      e.preventDefault();
      const x = e.pageX - container.offsetLeft;
      const walk = (x - startX) * 1.5;
      container.scrollLeft = scrollLeft - walk;
    });

    // Touch support
    let touchStartX = 0;
    let touchScrollLeft = 0;

    container.addEventListener("touchstart", e => {
      updateScrollable();
      if (!isScrollable) return;
      touchStartX = e.touches[0].pageX - container.offsetLeft;
      touchScrollLeft = container.scrollLeft;
    });

    container.addEventListener("touchmove", e => {
      if (!isScrollable) return;
      const x = e.touches[0].pageX - container.offsetLeft;
      const walk = (x - touchStartX) * 1.5;
      container.scrollLeft = touchScrollLeft - walk;
    });
  });
});
</script>


<script>
  layout_change('false');
  layout_theme_sidebar_change('dark');
  change_box_container('false');
  layout_caption_change('true');
  layout_rtl_change('false');
  preset_change('preset-1');
  main_layout_change('vertical');
</script>

</body>
</html>
