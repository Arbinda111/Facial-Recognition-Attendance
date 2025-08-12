import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
  plugins: [react()],
  server: {
    host: "localhost",   // or "0.0.0.0" to listen on all interfaces
    port: 5173,
    strictPort: true,    // fail if 5173 is taken (optional)
    open: false          // don’t auto-open browser
  },
  test: {
    globals: true,
    environment: "jsdom",
  },
});
