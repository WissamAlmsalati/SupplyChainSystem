# Serves the two prebuilt frontend apps with nginx: admin at /, customer at /customer/.
# ponytail: dists are built on the host (`npm run build`, customer needs
# VITE_BASE_PATH=/customer/) because npm registry fetches stall inside containers
# on this machine (OrbStack network). Upgrade path: restore a node builder
# stage (see frontend-*/Dockerfile) once that resolves.
FROM nginx:alpine

COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY frontend-admin/dist /usr/share/nginx/html
COPY frontend-customer/dist /usr/share/nginx/html/customer

EXPOSE 80
