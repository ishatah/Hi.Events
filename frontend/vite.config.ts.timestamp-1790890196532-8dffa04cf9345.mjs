// vite.config.ts
import { defineConfig } from "file:///C:/Users/Ibra/Documents/GitHub/Hi.Events/frontend/node_modules/vite/dist/node/index.js";
import { lingui } from "file:///C:/Users/Ibra/Documents/GitHub/Hi.Events/frontend/node_modules/@lingui/vite-plugin/dist/index.cjs";
import react from "file:///C:/Users/Ibra/Documents/GitHub/Hi.Events/frontend/node_modules/@vitejs/plugin-react/dist/index.js";
import { copy } from "file:///C:/Users/Ibra/Documents/GitHub/Hi.Events/frontend/node_modules/vite-plugin-copy/dist/vite-plugin-copy.js";
import { existsSync, readFileSync } from "fs";
import { resolve } from "path";
var __vite_injected_original_dirname = "C:\\Users\\Ibra\\Documents\\GitHub\\Hi.Events\\frontend";
function getVersion() {
  const candidates = [
    resolve(__vite_injected_original_dirname, "../VERSION"),
    resolve(__vite_injected_original_dirname, "../../VERSION"),
    "/app/VERSION"
  ];
  for (const path of candidates) {
    if (existsSync(path)) {
      return readFileSync(path, "utf-8").trim();
    }
  }
  return "unknown";
}
var vite_config_default = defineConfig({
  optimizeDeps: {
    include: ["react-router"]
  },
  server: {
    hmr: {
      port: 24678,
      protocol: "ws"
    }
  },
  plugins: [
    react({
      babel: {
        plugins: ["macros"]
      }
    }),
    lingui(),
    copy({
      targets: [{ src: "src/embed/widget.js", dest: "public" }],
      hook: "writeBundle"
    })
  ],
  define: {
    "__APP_VERSION__": JSON.stringify(getVersion())
  },
  ssr: {
    noExternal: ["react-helmet-async"]
  },
  css: {
    preprocessorOptions: {
      scss: {
        api: "modern-compiler"
      }
    }
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcudHMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxJYnJhXFxcXERvY3VtZW50c1xcXFxHaXRIdWJcXFxcSGkuRXZlbnRzXFxcXGZyb250ZW5kXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCJDOlxcXFxVc2Vyc1xcXFxJYnJhXFxcXERvY3VtZW50c1xcXFxHaXRIdWJcXFxcSGkuRXZlbnRzXFxcXGZyb250ZW5kXFxcXHZpdGUuY29uZmlnLnRzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy9DOi9Vc2Vycy9JYnJhL0RvY3VtZW50cy9HaXRIdWIvSGkuRXZlbnRzL2Zyb250ZW5kL3ZpdGUuY29uZmlnLnRzXCI7aW1wb3J0IHtkZWZpbmVDb25maWd9IGZyb20gXCJ2aXRlXCI7XHJcbmltcG9ydCB7bGluZ3VpfSBmcm9tIFwiQGxpbmd1aS92aXRlLXBsdWdpblwiO1xyXG5pbXBvcnQgcmVhY3QgZnJvbSBcIkB2aXRlanMvcGx1Z2luLXJlYWN0XCI7XHJcbmltcG9ydCB7Y29weX0gZnJvbSBcInZpdGUtcGx1Z2luLWNvcHlcIjtcclxuaW1wb3J0IHtleGlzdHNTeW5jLCByZWFkRmlsZVN5bmN9IGZyb20gXCJmc1wiO1xyXG5pbXBvcnQge3Jlc29sdmV9IGZyb20gXCJwYXRoXCI7XHJcblxyXG5mdW5jdGlvbiBnZXRWZXJzaW9uKCk6IHN0cmluZyB7XHJcbiAgICBjb25zdCBjYW5kaWRhdGVzID0gW1xyXG4gICAgICAgIHJlc29sdmUoX19kaXJuYW1lLCBcIi4uL1ZFUlNJT05cIiksXHJcbiAgICAgICAgcmVzb2x2ZShfX2Rpcm5hbWUsIFwiLi4vLi4vVkVSU0lPTlwiKSxcclxuICAgICAgICBcIi9hcHAvVkVSU0lPTlwiLFxyXG4gICAgXTtcclxuICAgIGZvciAoY29uc3QgcGF0aCBvZiBjYW5kaWRhdGVzKSB7XHJcbiAgICAgICAgaWYgKGV4aXN0c1N5bmMocGF0aCkpIHtcclxuICAgICAgICAgICAgcmV0dXJuIHJlYWRGaWxlU3luYyhwYXRoLCBcInV0Zi04XCIpLnRyaW0oKTtcclxuICAgICAgICB9XHJcbiAgICB9XHJcbiAgICByZXR1cm4gXCJ1bmtub3duXCI7XHJcbn1cclxuXHJcbmV4cG9ydCBkZWZhdWx0IGRlZmluZUNvbmZpZyh7XHJcbiAgICBvcHRpbWl6ZURlcHM6IHtcclxuICAgICAgICBpbmNsdWRlOiBbXCJyZWFjdC1yb3V0ZXJcIl1cclxuICAgIH0sXHJcbiAgICBzZXJ2ZXI6IHtcclxuICAgICAgICBobXI6IHtcclxuICAgICAgICAgICAgcG9ydDogMjQ2NzgsXHJcbiAgICAgICAgICAgIHByb3RvY29sOiBcIndzXCIsXHJcbiAgICAgICAgfSxcclxuICAgIH0sXHJcbiAgICBwbHVnaW5zOiBbXHJcbiAgICAgICAgcmVhY3Qoe1xyXG4gICAgICAgICAgICBiYWJlbDoge1xyXG4gICAgICAgICAgICAgICAgcGx1Z2luczogW1wibWFjcm9zXCJdLFxyXG4gICAgICAgICAgICB9LFxyXG4gICAgICAgIH0pLFxyXG4gICAgICAgIGxpbmd1aSgpLFxyXG4gICAgICAgIGNvcHkoe1xyXG4gICAgICAgICAgICB0YXJnZXRzOiBbe3NyYzogXCJzcmMvZW1iZWQvd2lkZ2V0LmpzXCIsIGRlc3Q6IFwicHVibGljXCJ9XSxcclxuICAgICAgICAgICAgaG9vazogXCJ3cml0ZUJ1bmRsZVwiLFxyXG4gICAgICAgIH0pLFxyXG4gICAgXSxcclxuICAgIGRlZmluZToge1xyXG4gICAgICAgIFwiX19BUFBfVkVSU0lPTl9fXCI6IEpTT04uc3RyaW5naWZ5KGdldFZlcnNpb24oKSksXHJcbiAgICB9LFxyXG4gICAgc3NyOiB7XHJcbiAgICAgICAgbm9FeHRlcm5hbDogW1wicmVhY3QtaGVsbWV0LWFzeW5jXCJdLFxyXG4gICAgfSxcclxuICAgIGNzczoge1xyXG4gICAgICAgIHByZXByb2Nlc3Nvck9wdGlvbnM6IHtcclxuICAgICAgICAgICAgc2Nzczoge1xyXG4gICAgICAgICAgICAgICAgYXBpOiBcIm1vZGVybi1jb21waWxlclwiLFxyXG4gICAgICAgICAgICB9XHJcbiAgICAgICAgfVxyXG4gICAgfVxyXG59KTtcclxuIl0sCiAgIm1hcHBpbmdzIjogIjtBQUFtVixTQUFRLG9CQUFtQjtBQUM5VyxTQUFRLGNBQWE7QUFDckIsT0FBTyxXQUFXO0FBQ2xCLFNBQVEsWUFBVztBQUNuQixTQUFRLFlBQVksb0JBQW1CO0FBQ3ZDLFNBQVEsZUFBYztBQUx0QixJQUFNLG1DQUFtQztBQU96QyxTQUFTLGFBQXFCO0FBQzFCLFFBQU0sYUFBYTtBQUFBLElBQ2YsUUFBUSxrQ0FBVyxZQUFZO0FBQUEsSUFDL0IsUUFBUSxrQ0FBVyxlQUFlO0FBQUEsSUFDbEM7QUFBQSxFQUNKO0FBQ0EsYUFBVyxRQUFRLFlBQVk7QUFDM0IsUUFBSSxXQUFXLElBQUksR0FBRztBQUNsQixhQUFPLGFBQWEsTUFBTSxPQUFPLEVBQUUsS0FBSztBQUFBLElBQzVDO0FBQUEsRUFDSjtBQUNBLFNBQU87QUFDWDtBQUVBLElBQU8sc0JBQVEsYUFBYTtBQUFBLEVBQ3hCLGNBQWM7QUFBQSxJQUNWLFNBQVMsQ0FBQyxjQUFjO0FBQUEsRUFDNUI7QUFBQSxFQUNBLFFBQVE7QUFBQSxJQUNKLEtBQUs7QUFBQSxNQUNELE1BQU07QUFBQSxNQUNOLFVBQVU7QUFBQSxJQUNkO0FBQUEsRUFDSjtBQUFBLEVBQ0EsU0FBUztBQUFBLElBQ0wsTUFBTTtBQUFBLE1BQ0YsT0FBTztBQUFBLFFBQ0gsU0FBUyxDQUFDLFFBQVE7QUFBQSxNQUN0QjtBQUFBLElBQ0osQ0FBQztBQUFBLElBQ0QsT0FBTztBQUFBLElBQ1AsS0FBSztBQUFBLE1BQ0QsU0FBUyxDQUFDLEVBQUMsS0FBSyx1QkFBdUIsTUFBTSxTQUFRLENBQUM7QUFBQSxNQUN0RCxNQUFNO0FBQUEsSUFDVixDQUFDO0FBQUEsRUFDTDtBQUFBLEVBQ0EsUUFBUTtBQUFBLElBQ0osbUJBQW1CLEtBQUssVUFBVSxXQUFXLENBQUM7QUFBQSxFQUNsRDtBQUFBLEVBQ0EsS0FBSztBQUFBLElBQ0QsWUFBWSxDQUFDLG9CQUFvQjtBQUFBLEVBQ3JDO0FBQUEsRUFDQSxLQUFLO0FBQUEsSUFDRCxxQkFBcUI7QUFBQSxNQUNqQixNQUFNO0FBQUEsUUFDRixLQUFLO0FBQUEsTUFDVDtBQUFBLElBQ0o7QUFBQSxFQUNKO0FBQ0osQ0FBQzsiLAogICJuYW1lcyI6IFtdCn0K
