/* ==========================================================================
   JAIBABA INTERLININGS — Main JavaScript
   Include loader, navigation, counters, forms, Swiper & AOS bootstrapping.
   ========================================================================== */

(function ($) {
  'use strict';

  /**
   * All page links/assets are plain relative paths so the site works from any
   * deployment path (domain root, a subfolder, moved later, etc.). The only
   * page depth this site has is: site root, and one level down in /product/.
   * ROOT_PREFIX is "../" when the current page lives in /product/, else "".
   * The shared includes (header/navbar/footer) are the same HTML injected on
   * pages at both depths, so their internal links use a literal "{{ROOT}}"
   * placeholder that gets swapped for ROOT_PREFIX right after fetching them.
   */
  var ROOT_PREFIX = /\/product\/[^/]*$/.test(window.location.pathname) ? '../' : '';

  /**
   * Loads reusable partials declared as <div data-include="includes/x.html"></div>
   */
  function loadIncludes(callback) {
    var targets = document.querySelectorAll('[data-include]');

    if (!targets.length) {
      callback && callback();
      return;
    }

    var pending = targets.length;

    targets.forEach(function (el) {
      var file = ROOT_PREFIX + el.getAttribute('data-include');

      fetch(file)
        .then(function (res) {
          if (!res.ok) throw new Error('Failed to load include: ' + file);
          return res.text();
        })
        .then(function (html) {
          el.outerHTML = html.split('{{ROOT}}').join(ROOT_PREFIX);
        })
        .catch(function (err) {
          console.error(err);
        })
        .finally(function () {
          pending -= 1;
          if (pending === 0) callback && callback();
        });
    });
  }

  function setActiveNav() {
    var path = window.location.pathname;
    var page = path.split('/').pop() || 'index.html';

    if (path.indexOf('/product/') !== -1) {
      page = 'products.html';
    }

    document.querySelectorAll('.navbar-main [data-page]').forEach(function (link) {
      if (link.getAttribute('data-page') === page) {
        link.classList.add('active');
        var parentItem = link.closest('.nav-item');
        if (parentItem) parentItem.classList.add('active');
      }
    });
  }

  function initHeaderScroll() {
    var header = document.querySelector('.site-header');
    if (!header) return;

    function onScroll() {
      if (window.scrollY > 30) header.classList.add('is-scrolled');
      else header.classList.remove('is-scrolled');
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function initMobileMenuClose() {
    var collapseEl = document.getElementById('mainNav');
    if (!collapseEl) return;

    collapseEl.querySelectorAll('.nav-link:not(.dropdown-toggle)').forEach(function (link) {
      link.addEventListener('click', function () {
        if (collapseEl.classList.contains('show') && window.bootstrap) {
          var instance = window.bootstrap.Collapse.getInstance(collapseEl) ||
            new window.bootstrap.Collapse(collapseEl, { toggle: false });
          instance.hide();
        }
      });
    });
  }

  function initBackToTop() {
    var btn = document.querySelector('.back-to-top');
    if (!btn) return;

    window.addEventListener('scroll', function () {
      if (window.scrollY > 500) btn.classList.add('is-visible');
      else btn.classList.remove('is-visible');
    }, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  function animateCounter(el) {
    var target = parseFloat(el.getAttribute('data-count'), 10);
    var duration = 1700;
    var start = null;

    function step(timestamp) {
      if (!start) start = timestamp;
      var progress = Math.min((timestamp - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.floor(eased * target);
      el.textContent = value.toLocaleString('en-IN');

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target.toLocaleString('en-IN');
      }
    }

    requestAnimationFrame(step);
  }

  function initCounters() {
    var counters = document.querySelectorAll('.counter[data-count]');
    if (!counters.length) return;

    if (!('IntersectionObserver' in window)) {
      counters.forEach(animateCounter);
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });

    counters.forEach(function (c) { observer.observe(c); });
  }

  function initPreloader() {
    var pre = document.querySelector('.preloader');
    if (!pre) return;

    window.addEventListener('load', function () {
      setTimeout(function () { pre.classList.add('loaded'); }, 200);
    });
  }

  function initSwipers() {
    if (typeof Swiper === 'undefined') return;

    document.querySelectorAll('.testimonial-swiper').forEach(function (el) {
      new Swiper(el, {
        loop: true,
        autoplay: { delay: 5500, disableOnInteraction: false },
        spaceBetween: 24,
        pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
        breakpoints: {
          0: { slidesPerView: 1 },
          768: { slidesPerView: 2 },
          1200: { slidesPerView: 3 }
        }
      });
    });

    document.querySelectorAll('.gallery-swiper').forEach(function (el) {
      new Swiper(el, {
        loop: true,
        autoplay: { delay: 4200, disableOnInteraction: false },
        spaceBetween: 18,
        pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
        breakpoints: {
          0: { slidesPerView: 1.15 },
          576: { slidesPerView: 2.2 },
          992: { slidesPerView: 4 }
        }
      });
    });

    document.querySelectorAll('.logo-swiper').forEach(function (el) {
      new Swiper(el, {
        loop: true,
        autoplay: { delay: 2000, disableOnInteraction: false },
        speed: 3500,
        spaceBetween: 50,
        allowTouchMove: false,
        breakpoints: {
          0: { slidesPerView: 2 },
          576: { slidesPerView: 3 },
          992: { slidesPerView: 5 }
        }
      });
    });
  }

  function initAOS() {
    if (typeof AOS === 'undefined') return;
    AOS.init({
      duration: 700,
      once: true,
      offset: 60,
      easing: 'ease-out-cubic'
    });
  }

  function showFormAlert(alertBox, success, message) {
    if (!alertBox) return;
    alertBox.style.display = 'block';
    alertBox.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
    alertBox.textContent = message;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function initContactForm() {
    var form = document.getElementById('enquiryForm');
    if (!form) return;

    var alertBox = document.getElementById('formAlert');
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn ? submitBtn.innerHTML : '';

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
      }

      var formData = new FormData(form);

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Sending <i class="fas fa-spinner fa-spin ms-1"></i>';
      }

      fetch(ROOT_PREFIX + 'php/contact-form.php', {
        method: 'POST',
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          showFormAlert(alertBox, !!data.success, data.message || 'Thank you. Your enquiry has been received.');
          if (data.success) {
            form.reset();
            form.classList.remove('was-validated');
          }
        })
        .catch(function () {
          showFormAlert(alertBox, false, 'We could not send your enquiry right now. Please call +91 9910555033 or email us directly.');
        })
        .finally(function () {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        });
    });
  }

  function initNewsletterForm() {
    var form = document.getElementById('newsletterForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('button');
      if (btn) {
        var original = btn.innerHTML;
        btn.innerHTML = 'Subscribed <i class="fas fa-check ms-1"></i>';
        setTimeout(function () { btn.innerHTML = original; }, 2500);
      }
      form.reset();
    });
  }

  function initYear() {
    document.querySelectorAll('[data-current-year]').forEach(function (el) {
      el.textContent = new Date().getFullYear();
    });
  }

  $(function () {
    loadIncludes(function () {
      setActiveNav();
      initHeaderScroll();
      initMobileMenuClose();
      initBackToTop();
      initAOS();
      initYear();
    });

    initCounters();
    initPreloader();
    initSwipers();
    initContactForm();
    initNewsletterForm();
  });

})(jQuery);
