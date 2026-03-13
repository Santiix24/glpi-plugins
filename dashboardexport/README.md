# Dashboard Export Plugin para GLPI

Plugin para GLPI que permite exportar dashboards con gráficas y datos a formato Excel XLSX.

## Características

- **Dashboard interactivo**: Visualiza estadísticas de tickets, computadores y usuarios con gráficas modernas
- **Exportación a Excel**: Genera archivos XLSX con todos los datos del dashboard
- **Múltiples hojas**: El archivo Excel incluye datos separados por categoría
- **Historial de exportaciones**: Registro de todas las exportaciones realizadas
- **Conexión configurable**: Permite configurar la conexión a diferentes servidores de base de datos

## Requisitos

- GLPI versión 10.0.0 o superior
- PHP 7.4 o superior
- Extensión PHP ZIP habilitada
- Extensión PHP GD habilitada (para gráficas)

## Instalación

### Método 1: Instalación manual

1. Copia la carpeta `dashboardexport` a `glpi/plugins/`
2. En GLPI, ve a **Configuración → Plugins**
3. Busca "Dashboard Export" en la lista
4. Haz clic en **Instalar**
5. Después de instalar, haz clic en **Activar**

### Método 2: Desde línea de comandos

```bash
# Navegar a la carpeta de plugins de GLPI
cd /ruta/a/glpi/plugins/

# Copiar el plugin
cp -r /ruta/descarga/dashboardexport ./

# Dar permisos correctos
chown -R www-data:www-data dashboardexport
chmod -R 755 dashboardexport
```

## Configuración

1. Ve a **Configuración → Plugins → Dashboard Export → Configuración**
2. Configura la conexión al servidor de base de datos:
   - Host del servidor (localhost o IP)
   - Puerto (por defecto 3306)
   - Base de datos (nombre de la BD de GLPI)
   - Usuario y contraseña
3. Usa el botón "Probar Conexión" para verificar
4. Guarda la configuración

## Uso

### Ver Dashboard

1. Ve a **Herramientas → Dashboard Export → Ver Dashboard**
2. Visualiza las estadísticas con gráficas interactivas:
   - Total de tickets, computadores y usuarios
   - Tickets por estado, prioridad, tipo
   - Tickets por mes (últimos 12 meses)
   - Computadores por sistema operativo y fabricante
   - Tabla de tickets recientes

### Exportar a Excel

**Desde el Dashboard:**
1. Haz clic en el botón verde "Exportar a Excel"
2. El archivo se generará y descargará automáticamente

**Desde la página de Exportación:**
1. Ve a **Herramientas → Dashboard Export → Exportar a Excel**
2. Selecciona los datos a incluir
3. Personaliza el nombre del archivo
4. Haz clic en "Generar y Descargar Excel"

## Estructura del archivo Excel generado

El archivo XLSX contiene las siguientes hojas:

1. **Dashboard** - Resumen general con métricas principales
2. **Tickets por Estado** - Desglose de tickets por estado
3. **Tickets por Prioridad** - Desglose de tickets por prioridad
4. **Tickets por Mes** - Histórico mensual de tickets
5. **Tickets por Categoría** - Top 10 categorías con más tickets
6. **Computadores** - Estadísticas de computadores por SO y fabricante
7. **Usuarios** - Estadísticas de usuarios por perfil y grupo
8. **Tickets Recientes** - Lista de los 20 tickets más recientes

## Estructura de archivos del plugin

```
dashboardexport/
├── setup.php              # Configuración principal del plugin
├── hook.php               # Hooks de instalación/desinstalación
├── README.md              # Este archivo
├── inc/
│   ├── menu.class.php     # Clase para el menú
│   ├── config.class.php   # Clase de configuración
│   ├── dashboard.class.php # Clase principal del dashboard
│   └── excel.class.php    # Clase de exportación a Excel
├── front/
│   ├── dashboard.php      # Página del dashboard
│   ├── export.php         # Página de exportación
│   ├── config.php         # Página de configuración
│   └── config.form.php    # Procesador del formulario de configuración
├── ajax/
│   ├── export.php         # Endpoint para exportación AJAX
│   ├── download.php       # Endpoint para descarga de archivos
│   └── testconnection.php # Endpoint para probar conexión
├── css/
│   └── dashboardexport.css # Estilos del plugin
├── js/
│   ├── dashboardexport.js # JavaScript principal
│   └── charts.js          # Configuración de gráficas Chart.js
└── locales/
    └── es_ES.php          # Traducciones al español
```

## Solución de problemas

### El plugin no aparece en la lista
- Verifica que la carpeta se llame exactamente `dashboardexport`
- Asegúrate de que el archivo `setup.php` esté en la raíz de la carpeta

### Error al generar Excel
- Verifica que la extensión PHP ZIP esté habilitada
- Comprueba los permisos de escritura en `glpi/files/_plugins/dashboardexport/exports/`

### Las gráficas no se muestran
- Verifica que Chart.js se cargue correctamente desde el CDN
- Comprueba la consola del navegador para errores de JavaScript

### Error de conexión a base de datos
- Verifica las credenciales en la configuración
- Asegúrate de que el servidor MySQL permita conexiones remotas (si aplica)
- Comprueba que el firewall permita la conexión al puerto configurado

## Soporte

Para reportar problemas o solicitar nuevas funcionalidades, contacta al administrador del sistema.

## Licencia

Este plugin está licenciado bajo GPLv3.

## Changelog

### Versión 1.0.0
- Versión inicial
- Dashboard con estadísticas de tickets, computadores y usuarios
- Exportación a Excel XLSX
- Gráficas interactivas con Chart.js
- Configuración de conexión a servidor
- Historial de exportaciones
