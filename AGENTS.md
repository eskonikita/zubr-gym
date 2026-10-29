# ZUBR GYM — project context

Before changing this project, read `docs/zubr/CONTEXT.md` and `cms/README.md`. The older planning documents in `docs/zubr/` describe historical stages, not the current implementation state.

- The working site is MODX 3 in `public/`, running at `http://zubr-gym.local/` through Open Server Panel. `npm run dev` serves only the static preview.
- Current live layout and content are stored in MODX templates, chunks, resource content and TVs. Editing static HTML alone does not update the CMS. Do not run the whole static import to apply a narrow change: it replaces manually edited template bodies.
- Keep database backups before migrations. Credentials and backups live outside the document root in ignored `cms/private/` and `cms/backups/`; never print or commit credentials.
- Shared contacts belong to ClientConfig, SEO metadata to the SEO TVs and common SEO code. Avoid adding repeated hardcoded values back into templates.
- Preserve the current design and approved page structure. Continue extracting reusable CMS fields/chunks incrementally when requested.
- The user explicitly enabled indexing on the local site. Do not reinstate a staging-only indexing block without a new request.
- Update `docs/zubr/CONTEXT.md` after significant changes so future sessions understand the current state and next steps.

- Keep template/chunk/resource HTML readable with line breaks and literal tab indentation (Ace tab size 4, soft tabs off). Preserve inline prose and MODX expressions; do not minify CMS element source.

- Use English chunk names. All page templates begin with `[[$head]]`; keep shared document markup in that chunk and page-specific head conditions in `cms/elements/PageHead.php`. The scroll-to-top button belongs to `footer`, not to individual templates.

- End each page template with `[[$footer]]`: it includes mobileMenu and closes body/html. Put optional dialogs before that call. The skip-link belongs at the start of header.
