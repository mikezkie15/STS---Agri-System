// Main JavaScript for Barangay Agri-Market Platform

// Global variables
let currentUser = null;
let products = [];
let announcements = [];

// Initialize the application
document.addEventListener("DOMContentLoaded", function () {
  loadRecentProducts();
  loadRecentAnnouncements();
  checkAuthStatus();
});

// Load recent products for homepage
async function loadRecentProducts() {
  try {
    const response = await fetch("api/products.php?limit=6");
    const data = await response.json();

    if (data.success) {
      products = data.products;
      displayRecentProducts(products);
    }
  } catch (error) {
    console.error("Error loading products:", error);
  }
}

// Display recent products on homepage
function displayRecentProducts(products) {
  const container = document.getElementById("recent-products");
  if (!container) return;

  if (products.length === 0) {
    container.innerHTML =
      '<div class="col-12 text-center"><p class="text-muted">No products available yet.</p></div>';
    return;
  }

  container.innerHTML = products
    .map(
      (product) => `
        <div class="col-md-4 mb-4">
            <div class="card product-card h-100">
                        <img src="${
                          product.image || "assets/images/placeholder.svg"
                        }" class="card-img-top product-image" alt="${
        product.name
      }">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">${product.name}</h5>
                    <p class="card-text text-muted">${
                      product.description || "Fresh produce from local farmer"
                    }</p>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="product-price">₱${parseFloat(
                              product.price
                            ).toFixed(2)}</span>
                            <span class="badge bg-light text-dark">${
                              product.quantity
                            } ${product.unit}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                ${product.seller_name}
                                ${
                                  product.is_verified
                                    ? '<span class="verified-badge ms-1">✓</span>'
                                    : ""
                                }
                            </small>
                            <button class="btn btn-sm btn-outline-success" onclick="viewProduct(${
                              product.id
                            })">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `
    )
    .join("");
}

// Load recent announcements
async function loadRecentAnnouncements() {
  try {
    const response = await fetch("api/announcements.php?limit=3");
    const data = await response.json();

    if (data.success) {
      announcements = data.announcements;
      displayRecentAnnouncements(announcements);
    }
  } catch (error) {
    console.error("Error loading announcements:", error);
  }
}

// Display recent announcements
function displayRecentAnnouncements(announcements) {
  const container = document.getElementById("recent-announcements");
  if (!container) return;

  if (announcements.length === 0) {
    container.innerHTML =
      '<div class="col-12 text-center"><p class="text-muted">No announcements available.</p></div>';
    return;
  }

  container.innerHTML = announcements
    .map(
      (announcement) => `
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-title text-success">${
                      announcement.title
                    }</h6>
                    <p class="card-text">${announcement.content.substring(
                      0,
                      100
                    )}${announcement.content.length > 100 ? "..." : ""}</p>
                    <small class="text-muted">
                        <i class="fas fa-calendar me-1"></i>
                        ${formatDate(announcement.created_at)}
                    </small>
                </div>
            </div>
        </div>
    `
    )
    .join("");
}

// Check authentication status
function checkAuthStatus() {
  const token = localStorage.getItem("auth_token");
  if (token) {
    // Validate token with server
    validateToken(token);
  }
}

// Validate authentication token
async function validateToken(token) {
  try {
    const response = await fetch("api/auth.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ action: "validate", token: token }),
    });

    const data = await response.json();
    if (data.success) {
      currentUser = data.user;
      updateNavigation();
    } else {
      localStorage.removeItem("auth_token");
    }
  } catch (error) {
    console.error("Error validating token:", error);
    localStorage.removeItem("auth_token");
  }
}

// Update navigation based on user status
function updateNavigation() {
  const nav = document.querySelector("#navbarNav ul");
  if (!nav || !currentUser) return;

  // Remove login link and add user-specific links
  const loginLink = nav.querySelector('a[href="login.html"]');
  if (loginLink) {
    loginLink.parentElement.remove();
  }

  // Add user menu
  const userMenu = document.createElement("li");
  userMenu.className = "nav-item dropdown";
  userMenu.innerHTML = `
        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="fas fa-user me-1"></i>${currentUser.name}
        </a>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="dashboard.html">Dashboard</a></li>
            <li><a class="dropdown-item" href="profile.html">Profile</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" onclick="logout()">Logout</a></li>
        </ul>
    `;
  nav.appendChild(userMenu);
}

// Logout function
function logout() {
  localStorage.removeItem("auth_token");
  currentUser = null;
  location.reload();
}

// View product details
function viewProduct(productId) {
  window.location.href = `product-details.html?id=${productId}`;
}

// Format date for display
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

// Show loading spinner
function showLoading(element) {
  element.innerHTML =
    '<div class="text-center"><div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div></div>';
}

// Show error message
function showError(message, element) {
  element.innerHTML = `<div class="alert alert-danger" role="alert">${message}</div>`;
}

// Show success message
function showSuccess(message, element) {
  element.innerHTML = `<div class="alert alert-success" role="alert">${message}</div>`;
}

// Form validation
function validateForm(form) {
  const inputs = form.querySelectorAll(
    "input[required], textarea[required], select[required]"
  );
  let isValid = true;

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      input.classList.add("is-invalid");
      isValid = false;
    } else {
      input.classList.remove("is-invalid");
    }
  });

  return isValid;
}

// Clear form validation
function clearFormValidation(form) {
  const inputs = form.querySelectorAll(".is-invalid");
  inputs.forEach((input) => input.classList.remove("is-invalid"));
}

// Handle form submission
function handleFormSubmit(form, endpoint, successCallback) {
  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (!validateForm(form)) {
      return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (result.success) {
        if (successCallback) {
          successCallback(result);
        } else {
          showSuccess(
            result.message || "Operation completed successfully!",
            form
          );
        }
      } else {
        showError(
          result.message || "An error occurred. Please try again.",
          form
        );
      }
    } catch (error) {
      console.error("Error:", error);
      showError(
        "Network error. Please check your connection and try again.",
        form
      );
    }
  });
}

// Search functionality
function searchProducts(query) {
  const filteredProducts = products.filter(
    (product) =>
      product.name.toLowerCase().includes(query.toLowerCase()) ||
      product.description.toLowerCase().includes(query.toLowerCase()) ||
      product.seller_name.toLowerCase().includes(query.toLowerCase())
  );

  return filteredProducts;
}

// Filter products by category
function filterProductsByCategory(category) {
  if (category === "all") {
    return products;
  }
  return products.filter((product) => product.category === category);
}

// Sort products
function sortProducts(products, sortBy) {
  switch (sortBy) {
    case "price-low":
      return products.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
    case "price-high":
      return products.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
    case "name":
      return products.sort((a, b) => a.name.localeCompare(b.name));
    case "date-new":
      return products.sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at)
      );
    default:
      return products;
  }
}

// Export functions for use in other scripts
window.AgriMarket = {
  loadRecentProducts,
  loadRecentAnnouncements,
  checkAuthStatus,
  validateToken,
  updateNavigation,
  logout,
  viewProduct,
  formatDate,
  showLoading,
  showError,
  showSuccess,
  validateForm,
  clearFormValidation,
  handleFormSubmit,
  searchProducts,
  filterProductsByCategory,
  sortProducts,
};
