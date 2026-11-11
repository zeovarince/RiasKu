import { createRouter, createWebHistory } from "vue-router";

const routes = [
  {
    path: "/",
    redirect: "/products",
  },
  {
    path: "/login",
    name: "login",
    component: () => import("../views/auth/LoginView.vue"),
  },
  {
    path: "/products",
    name: "products",
    component: () => import("../views/customer/ProductListView.vue"),
  },
  {
    path: "/cart",
    name: "cart",
    component: () => import("../views/customer/CartView.vue"),
  },
  {
    path: "/admin/dashboard",
    name: "admin-dashboard",
    component: () => import("../views/admin/DashboardView.vue"),
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
