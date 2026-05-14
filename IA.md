# Uso de IA en este proyecto

## 1. Herramientas utilizadas

| Herramienta | Versión / Modelo | Modo de uso | Aprox. % del trabajo |
|Claude code|4.6|Web|40-50%|
|Claude Sonnet|4.6|Extension de VScode|5-10%|

## 2. Configuración del proyecto

### CLAUDE.md / AGENTS.md
No tengo por que lo he usado directamente desde el navegador

### settings.json u otra configuración equivalente
No se modificó ninguna configuración. Se usó la configuración por defecto
de Claude Code (web). No hay archivo settings.json en el repo ni permisos
personalizados.

## 3. Skills personalizadas

Ninguna.

## 4. Slash commands personalizados

Ninguno.

## 5. Sub-agentes invocados

No se usaron Plan Mode ni sub-agentes personalizados. Claude Code invocó
sub-agentes internamente de forma automática en algunas búsquedas de código,
pero no hay definiciones guardadas en el repo.

## 6. MCPs (Model Context Protocol)

No se conectó ningún MCP server.

Con más tiempo, habría sido útil conectar **context7** para consultar la
documentación oficial de PrestaShop 1.7 y evitar que Claude inventara
nombres de hooks o métodos inexistentes.

## 7. Prompts importantes

### Prompt 1
- **Herramienta:** Claude Code
- **Prompt:** `este modulo de prestashop no carga el menu que crea en catalogo -> Product badges`
- **Qué generó:** Diagnóstico del sistema de Tabs de PS 1.7. Explicó que `class_name` en la tabla `tab` nunca debe incluir el sufijo "Controller" porque PS lo añade al hacer el dispatch. Generó el fix en `installTab()` cambiando `'AdminProductBadgesController'` a `'AdminProductBadges'`, con un bucle de limpieza para eliminar tabs con el nombre incorrecto antes de crear el nuevo.
- **Qué hice con el output:** Acepté tal cual. El menú apareció inmediatamente tras reinstalar el módulo.

### Prompt 2
- **Herramienta:** Claude Code
- **Prompt:** `donde se lo asigno a un producto` / `no me aparece en el admin`
- **Qué generó:** El panel de asignación dentro del formulario de edición de producto vía `hookDisplayAdminProductsMainStepLeftColumnBottom`. Generó `assign_tab.tpl`, el método AJAX `ajaxProcessSaveProductBadges` en el controlador, y el JS del formulario de checkboxes.
- **Qué hice con el output:** Necesitó una segunda iteración porque el template no cargaba. Claude diagnosticó que `Module::display()` solo busca en `views/templates/hook/` y que en frontend la política de seguridad de Smarty bloquea paths absolutos, pero en admin sí funciona `$this->context->smarty->fetch($this->local_path . '...')`. Acepté la solución final.

### Prompt 3
- **Herramienta:** Claude Code
- **Prompt:** `No se muestran en la card de producto sobre la imagen` (repetido varias veces con distintos ajustes)
- **Qué generó:** Sucesivas hipótesis: el `<div>` dentro de `<ul.product-flags>` era HTML inválido (fix: cambiar a `<li>`), problema con el clon de Smarty en `getCurrentSubTemplate()` (fix: generar HTML directamente en PHP), y finalmente código de debug que devolvía una cadena visible al principio del hook.
- **Qué hice con el output:** Cada fix lo apliqué. El debug confirmó que el hook `displayProductFlags` nunca se disparaba en mi tema — el problema no era el código sino que el tema no llama al hook en sus cards.

### Prompt 4
- **Herramienta:** Claude Code
- **Prompt:** `ahora se muestra bien en el buscador y en la categoría, falta en home y Ficha del producto que siguen sin mostrarse `
- **Qué generó:** Dos soluciones concretas: (1) El fallback AJAX ya existente en `fetchMissing()` cubría los widgets de home leyendo `[data-id-product]` del DOM en runtime. (2) Para la ficha de producto, donde no hay `[data-id-product]` en el contenedor de imagen, detectar el ID desde `input[name="id_product"]` e inyectar en `.images-container`. Ambas modificaciones en `productbadges.js`.
- **Qué hice con el output:** Acepté tal cual. Funcionó en el siguiente test.

### Prompt 5
- **Herramienta:** Claude Code
- **Prompt:** `se puede separar en más commits necesito esto: Historial de commits que cuente cómo fuiste construyendo el módulo`
- **Qué generó:** Plan de 5 commits atómicos por capas lógicas (scaffold+DB, admin CRUD, frontend JS, traducciones, docs) y los ejecutó directamente, incluyendo la detección y eliminación de un archivo `AdminProductBadges.php` espurio que tenía contenido de `index.php` con nombre incorrecto.
- **Qué hice con el output:** Acepté el plan y la ejecución. El historial resultante refleja fielmente la evolución del módulo.

## 8. Errores de la IA que detecté

### Error 1 — Tab `class_name` con sufijo "Controller"
- **Qué generó la IA (mal):** `$tab->class_name = 'AdminProductBadgesController'` en `installTab()`.
- **Por qué estaba mal:** PS appends "Controller" al hacer dispatch, por lo que buscaba la clase `AdminProductBadgesControllerController`, que no existe. El resultado era una página en blanco al abrir el menú.
- **Cómo lo corregiste:** Lo detecté porque el menú no cargaba. Claude diagnosticó la causa cuando se lo reporté y generó el fix (`'AdminProductBadges'`). No lo detecté antes de ejecutar — lo vi en producción.

### Error 2 — `<div>` dentro de `<ul class="product-flags">`
- **Qué generó la IA (mal):** El primer render de badges usaba un `<div class="pb-badges-wrapper">` como hijo directo de `<ul.product-flags>`.
- **Por qué estaba mal:** HTML inválido — un `<div>` no puede ser hijo de `<ul>`. El navegador expulsa el nodo fuera del `<ul>`, rompiendo el posicionamiento CSS sobre la imagen.
- **Cómo lo corregiste:** Claude lo detectó en una iteración posterior y cambió el output a `<li>` elements, que es el tipo correcto de hijo para `<ul.product-flags>`.

### Error 3 — Asumió que `displayProductFlags` se dispararía en cualquier tema
- **Qué generó la IA (mal):** Implementó toda la lógica de display frontend sobre `hookDisplayProductFlags` como hook principal, sin advertir que muchos temas no lo llaman en sus templates de card.
- **Por qué estaba mal:** El tema activo en mi instalación no incluye `{hook h='displayProductFlags'}` en su `product.tpl`. El hook nunca se disparaba y el código era técnicamente correcto pero inaccesible. Se perdieron varios ciclos de debugging en código que no tenía ningún bug.
- **Cómo lo corregiste:** Solo fue evidente tras añadir un `return 'DEBUG';` al inicio del hook y confirmar que el string nunca aparecía. A partir de ahí Claude propuso el pivot a JS injection.

### Error 4 — Path incorrecto en `Module::display()` para el template admin
- **Qué generó la IA (mal):** `$this->display(__FILE__, 'views/templates/admin/assign/assign_tab.tpl')`.
- **Por qué estaba mal:** `Module::display()` solo resuelve paths bajo `views/templates/hook/`. Cualquier otra ruta falla silenciosamente.
- **Cómo lo corregiste:** Claude lo corrigió en la siguiente iteración usando `$this->context->smarty->fetch($this->local_path . 'views/templates/admin/assign/assign_tab.tpl')`, que sí acepta paths absolutos en contexto admin.

### Error 5 — Smarty clone en rendering por producto
- **Qué generó la IA (mal):** Primera versión de `renderBadgesForProduct()` usaba `$this->context->smarty->assign([...])` + `$this->display(__FILE__, 'badges.tpl')`.
- **Por qué estaba mal:** `Module::getCurrentSubTemplate()` clona `$this->context->smarty` en la primera llamada y reutiliza el clon. Los `assign()` posteriores en el objeto original no llegan al clon, por lo que todos los productos a partir del segundo mostraban los datos del primero.
- **Cómo lo corregiste:** Claude propuso generar el HTML directamente en PHP sin Smarty, evitando el problema por completo.

### Error 6 — README con nombres de hooks inventados
- **Qué generó la IA (mal):** El README inicial documentaba `displayProductListingHook` y `displayProductPriceBlock` como los hooks usados para mostrar badges en frontend.
- **Por qué estaba mal:** Esos hooks no existen en la implementación real. Los hooks usados son `displayBeforeBodyClosingTag`, `displayHeader` y `displayProductFlags`.
- **Cómo lo corregiste:** Lo detecté al revisar el README contra el código. Claude lo corrigió en la siguiente iteración del documento.


## 9. Partes que NO usé IA

### Estructura base del módulo
El esqueleto inicial — nombres de archivos, organización de directorios, qué clases crear y cómo llamarlas — lo planteé yo antes de implicar a la IA. Claude refactorizó y completó el código después, pero la estructura de partida la decidí yo.

### Configuración del entorno
Instalación de XAMPP, PrestaShop 1.7.8.11 y configuración de la base de datos local. Lo hice a mano porque es un procedimiento conocido y delegar el setup de entorno a una IA sin acceso real a la máquina no tiene sentido práctico.

### Testing y QA
Todo el ciclo de prueba fue manual: instalar/desinstalar el módulo desde el back office, crear badges, asignarlos a productos, navegar por categoría, búsqueda, home y ficha de producto para verificar que los badges aparecían correctamente. La IA no tiene acceso al navegador ni a la instalación PS — la única forma de validar era hacerlo yo.

## 10. Reflexión final

### ¿Qué te ahorró la IA en este ejercicio?

Todo el boilerplate de PS: la estructura de `ObjectModel` con `multilang`, el `ModuleAdminController` con `HelperForm`, el sistema de Tabs, las queries con `DbQuery`, el scaffolding de install/uninstall. Sin IA habría necesitado consultar documentación y ejemplos para cada uno de esos patrones. El tiempo de desarrollo se redujo considerablemente en la parte de "escribir código que sé cómo debe quedar pero que lleva tiempo teclear".

También fue útil en el pivot de arquitectura del frontend: cuando quedó claro que `displayProductFlags` no funcionaba con mi tema, Claude propuso el enfoque de JS injection + `window.pbData` + AJAX fallback de forma completa, con todos los casos cubiertos (listing, home, ficha). Yo solo habría llegado a esa solución después de mucho más tiempo de investigación.

### ¿En qué te entorpeció o te llevó por mal camino?

El error más caro fue confiar en `displayProductFlags` como mecanismo principal sin verificarlo antes. Claude no advirtió que ese hook no lo implementan todos los temas, y yo no lo cuestioné. Se perdieron varios ciclos de debugging en código sin ningún bug — el problema era simplemente que el hook nunca se llamaba. Si hubiera empezado con un `return 'test';` al inicio del hook me habría ahorrado esas iteraciones.

En general, la IA genera código que parece correcto y compila sin errores, lo que hace que sea fácil no cuestionarlo hasta que falla en runtime. El coste de confiar demasiado en el output sin validarlo es más alto en un framework como PS, donde los errores son silenciosos y el ciclo de feedback requiere reinstalar el módulo.

### ¿Qué cambiarías de tu flujo con IA si lo repitieras?

1. **Conectar context7** antes de empezar para que Claude consulte la documentación real de PS 1.7 en lugar de trabajar desde su training data. Evitaría nombres de hooks inventados y asumir comportamientos que dependen de la versión.
2. **Validar hooks con un return temprano** antes de construir la implementación completa. Un `return 'HOOK_FIRED';` al inicio de cada hook nuevo confirma en dos minutos que PS lo está llamando.
3. **Pedir a Claude que liste los riesgos conocidos de PS antes de cada feature**, no solo que genere el código. En este proyecto los problemas vinieron de particularidades de PS (Tab routing, Smarty clone, template paths) que Claude conocía pero no advirtió hasta que el error ya había ocurrido.
