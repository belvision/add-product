-- eMall categories reference (import from eMall API; id_embedding filled later from Qdrant).
-- Migration: 0005_category_emall

CREATE TABLE IF NOT EXISTS public.category_emall (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT NOT NULL,
    title_cat TEXT NOT NULL,
    path TEXT NULL,
    parent_id BIGINT NULL,
    statuse BIGINT NULL,
    disabled TEXT NULL,
    id_embedding BIGINT NULL,
    id_type BIGINT NULL,
    type_name TEXT NULL,
    CONSTRAINT uq_category_emall_category_id UNIQUE (category_id)
);

CREATE INDEX IF NOT EXISTS idx_category_emall_category_id ON public.category_emall (category_id);
CREATE INDEX IF NOT EXISTS idx_category_emall_parent_id ON public.category_emall (parent_id);
CREATE INDEX IF NOT EXISTS idx_category_emall_id_embedding ON public.category_emall (id_embedding) WHERE id_embedding IS NOT NULL;

COMMENT ON TABLE public.category_emall IS 'eMall category reference; filled by emall_import_categories.php; category_id = emall_id from API and from Qdrant payload.emall_id';
COMMENT ON COLUMN public.category_emall.category_id IS 'emall_id: real eMall category ID (from API id and Qdrant payload.emall_id)';
