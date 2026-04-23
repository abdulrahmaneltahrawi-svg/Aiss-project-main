      // ربط العضويات بالسلة عبر localStorage (نفس منطق السلة في المتجر)
      let cart = JSON.parse(localStorage.getItem("myCart")) || [];

      function updateCartBadgeCount() {
        const badge = document.getElementById("cart-count");
        if (!badge) return;
        badge.innerText = cart.length;
        badge.style.display = cart.length > 0 ? "flex" : "none";
      }

      window.addToCart = function addToCart(item) {
        let itemData = item;
        if (typeof item === "string") {
          itemData = JSON.parse(item);
        }

        cart.push(itemData);
        localStorage.setItem("myCart", JSON.stringify(cart));
        updateCartBadgeCount();

        // إظهار النافذة المنبثقة إذا كانت موجودة في الصفحة
        const popup = document.getElementById("cart-popup");
        if (popup) {
          const titleEl = document.getElementById("popup-title");
          const imgEl = document.getElementById("popup-img");
          const priceEl = document.getElementById("popup-price");
          
          if (titleEl) titleEl.innerText = `تمت الإضافة للسلة: ${itemData.title || ""}`;
          if (imgEl) imgEl.src = itemData.img || "assets/icons/logo.webp";
          if (priceEl) priceEl.innerText = itemData.price ? `${itemData.price} د.ا` : "";

          popup.classList.remove("hidden");
          
          // إخفاء تلقائي بعد 4 ثوانٍ
          setTimeout(() => {
            popup.classList.add("hidden");
          }, 4000);
        }
      };

      window.closeCartPopup = function closeCartPopup() {
        const popup = document.getElementById("cart-popup");
        if (popup) popup.classList.add("hidden");
      };

      // تحديث الشارة عند فتح الصفحة
      updateCartBadgeCount();