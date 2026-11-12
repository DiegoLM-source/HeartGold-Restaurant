# Despliegue en Render

El proyecto está preparado como servicio Docker de Laravel. Render compila los recursos Vite y ejecuta Apache con el directorio público de Laravel como document root. La aplicación usa Supabase para sus datos; no requiere crear otra base en Render.

## Publicación inicial

1. Sube este repositorio a GitHub. La raíz del repositorio debe contener `render.yaml` y `Dockerfile`.
2. En Render, crea un **Blueprint** y conecta ese repositorio.
3. Cuando Render solicite los secretos, introduce `SUPABASE_URL` y `SUPABASE_ANON_KEY` desde la configuración de Supabase. No uses la clave `service_role`.
4. Render generará `APP_KEY` y publicará el servicio. El contenedor obtiene su URL pública de `RENDER_EXTERNAL_URL`.
5. Comprueba `https://<servicio>.onrender.com/up` y luego inicia sesión con una cuenta existente de Supabase.
6. En Supabase, agrega esa URL a **Authentication → URL Configuration → Site URL** para que los enlaces de confirmación de registro regresen al sitio publicado.

## Configuración incluida

- `APP_ENV=production` y `APP_DEBUG=false`.
- Cookies de sesión seguras y sesión en archivos.
- Caché en archivos y cola síncrona; no se necesita Redis ni un worker separado para el código actual.
- Comprobación de salud en `/up`.
- El servicio usa el plan gratuito de Render. Ese plan puede suspender el servicio por inactividad; la primera carga posterior puede tardar y las sesiones en archivos se pierden cuando el contenedor se reinicia.

La aplicación no ejecuta migraciones Laravel en el despliegue: el esquema y los datos pertenecen a Supabase.
