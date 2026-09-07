# Serves the two prebuilt frontend apps with nginx: admin at /, cafe at /cafe/.
# ponytail: dists are built on the host (`npm run build`, cafe needs
# VITE_BASE_PATH=/cafe/) because npm registry fetches stall inside containers
# on this machine (OrbStack network). Upgrade path: restore a node builder
# stage (see frontend-*/Dockerfile) once that resolves.
FROM nginx:alpine

COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY frontend-admin/dist /usr/share/nginx/html
COPY frontend-cafe/dist /usr/share/nginx/html/cafe

EXPOSE 80
