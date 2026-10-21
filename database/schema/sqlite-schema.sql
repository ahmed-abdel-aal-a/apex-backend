CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "attributes"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "values" text not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "parent_id" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "is_active" tinyint(1) not null default '1',
  "slug" varchar,
  "seo_title" varchar,
  "seo_description" text,
  "meta_keywords" varchar,
  "canonical_url" varchar,
  "og_image" varchar,
  "is_indexable" tinyint(1) not null default '1',
  foreign key("parent_id") references "categories"("id") on delete restrict
);
CREATE TABLE IF NOT EXISTS "products"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description" text,
  "category_id" integer not null,
  "type" varchar check("type" in('simple', 'variable')) not null default 'simple',
  "price" numeric not null,
  "discount_price" numeric,
  "sku" varchar,
  "barcode" varchar,
  "track_inventory" tinyint(1) not null default '0',
  "quantity" integer,
  "weight" numeric,
  "seo_title" varchar,
  "seo_description" text,
  "created_at" datetime,
  "updated_at" datetime,
  "images" text,
  "slug" varchar,
  "is_active" tinyint(1) not null default '1',
  "meta_keywords" varchar,
  "canonical_url" varchar,
  "og_image" varchar,
  "is_indexable" tinyint(1) not null default '1',
  "schema_markup" text,
  foreign key("category_id") references "categories"("id") on delete restrict
);
CREATE UNIQUE INDEX "products_sku_unique" on "products"("sku");
CREATE UNIQUE INDEX "products_barcode_unique" on "products"("barcode");
CREATE TABLE IF NOT EXISTS "product_variants"(
  "id" integer primary key autoincrement not null,
  "product_id" integer not null,
  "name" varchar,
  "price" numeric not null,
  "discount_price" numeric,
  "sku" varchar,
  "track_inventory" tinyint(1) not null default '0',
  "quantity" integer,
  "attributes" text not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE UNIQUE INDEX "product_variants_sku_unique" on "product_variants"("sku");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" text not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE INDEX "personal_access_tokens_expires_at_index" on "personal_access_tokens"(
  "expires_at"
);
CREATE UNIQUE INDEX "products_slug_unique" on "products"("slug");
CREATE TABLE IF NOT EXISTS "customers"(
  "id" varchar not null,
  "store_id" integer not null,
  "session_id" varchar,
  "name" varchar,
  "phone" varchar,
  "email" varchar,
  "ip_address" varchar,
  "source" varchar,
  "device" varchar,
  "total_orders" integer not null default '0',
  "total_spent" numeric not null default '0',
  "first_seen" datetime,
  "last_seen" datetime,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "status" varchar check("status" in('active', 'banned')) not null default 'active',
  "is_guest" tinyint(1) not null default '1',
  "password" varchar,
  "email_verified_at" datetime,
  "remember_token" varchar,
  "visits_count" integer not null default '1',
  primary key("id")
);
CREATE UNIQUE INDEX "customers_store_id_email_unique" on "customers"(
  "store_id",
  "email"
);
CREATE UNIQUE INDEX "customers_store_id_phone_unique" on "customers"(
  "store_id",
  "phone"
);
CREATE INDEX "customers_store_id_index" on "customers"("store_id");
CREATE INDEX "customers_session_id_index" on "customers"("session_id");
CREATE TABLE IF NOT EXISTS "customer_browsing_histories"(
  "id" varchar not null,
  "store_id" integer not null,
  "customer_id" varchar not null,
  "path" varchar not null,
  "title" varchar not null,
  "type" varchar check("type" in('enter', 'view', 'add_to_cart', 'checkout', 'purchase', 'exit')) not null,
  "visited_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade,
  primary key("id")
);
CREATE INDEX "customer_browsing_histories_store_id_index" on "customer_browsing_histories"(
  "store_id"
);
CREATE TABLE IF NOT EXISTS "store_settings"(
  "id" integer primary key autoincrement not null,
  "manual_confirmation_required" tinyint(1) not null default '1',
  "whatsapp_template" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "cart_discounts"(
  "id" integer primary key autoincrement not null,
  "cart_id" integer not null,
  "code" varchar not null,
  "type" varchar check("type" in('percentage', 'fixed')) not null default 'percentage',
  "value" numeric not null,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("cart_id") references "carts"("id") on delete cascade
);
CREATE UNIQUE INDEX "cart_discounts_code_unique" on "cart_discounts"("code");
CREATE TABLE IF NOT EXISTS "cart_contact_histories"(
  "id" integer primary key autoincrement not null,
  "cart_id" integer not null,
  "type" varchar check("type" in('whatsapp', 'email', 'sms', 'push')) not null,
  "status" varchar check("status" in('sent', 'delivered', 'failed', 'clicked')) not null default 'sent',
  "sent_at" datetime not null default CURRENT_TIMESTAMP,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("cart_id") references "carts"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "abandoned_carts_settings"(
  "id" integer primary key autoincrement not null,
  "store_id" integer not null,
  "consider_abandoned_after_minutes" integer not null default '60',
  "frequency_capping_hours" integer not null default '24',
  "enable_push_notifications" tinyint(1) not null default '0',
  "push_notification_title" varchar,
  "push_notification_body" varchar,
  "whatsapp_template" text,
  "email_subject" varchar,
  "email_template" text,
  "enable_discounts" tinyint(1) not null default '1',
  "discount_type" varchar check("discount_type" in('percentage', 'fixed')) not null default 'percentage',
  "discount_value" numeric not null default '10',
  "anti_abuse_days" integer not null default '30',
  "exclude_sale_items" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "abandoned_carts_settings_store_id_index" on "abandoned_carts_settings"(
  "store_id"
);
CREATE TABLE IF NOT EXISTS "faq_questions"(
  "id" varchar not null,
  "section_id" varchar not null,
  "question" text not null,
  "answer" text not null,
  "is_active" tinyint(1) not null default '1',
  "order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("section_id") references "faq_sections"("id") on delete cascade,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "faq_sections"(
  "id" varchar not null,
  "title" varchar not null,
  "order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "chat_settings"(
  "id" integer primary key autoincrement not null,
  "is_message_enabled" tinyint(1) not null default '1',
  "message_text" text,
  "display_location" varchar not null default 'chat_only',
  "theme_color" varchar not null default '#f59e0b',
  "created_at" datetime,
  "updated_at" datetime,
  "whatsapp_number" varchar,
  "whatsapp_enabled" tinyint(1) not null default '1',
  "whatsapp_default_message" text
);
CREATE TABLE IF NOT EXISTS "chat_conversations"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "uuid" varchar not null,
  "is_online" tinyint(1) not null default '1',
  "unread_count" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "chat_conversations_uuid_unique" on "chat_conversations"(
  "uuid"
);
CREATE TABLE IF NOT EXISTS "chat_messages"(
  "id" integer primary key autoincrement not null,
  "conversation_id" integer not null,
  "text" text not null,
  "sender" varchar not null,
  "is_image" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("conversation_id") references "chat_conversations"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "settings"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "value" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "settings_key_unique" on "settings"("key");
CREATE TABLE IF NOT EXISTS "shipping_companies"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "description" text,
  "is_active" tinyint(1) not null default '0',
  "is_configured" tinyint(1) not null default '0',
  "cod_supported" tinyint(1) not null default '0',
  "credentials" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "shipping_companies_code_unique" on "shipping_companies"(
  "code"
);
CREATE TABLE IF NOT EXISTS "payment_gateways"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "name" varchar not null,
  "type" text,
  "is_active" tinyint(1) not null default '0',
  "is_configured" tinyint(1) not null default '0',
  "credentials" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "payment_gateways_code_unique" on "payment_gateways"(
  "code"
);
CREATE TABLE IF NOT EXISTS "shipping_zones"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "delivery_days" varchar,
  "rate" numeric not null default '0',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "printing_product_types"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "description" text,
  "icon" varchar,
  "is_active" tinyint(1) not null default '1',
  "allow_custom_name" tinyint(1) not null default '0',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE TABLE IF NOT EXISTS "printing_types"(
  "id" integer primary key autoincrement not null,
  "printing_product_type_id" integer not null,
  "name" varchar not null,
  "description" text,
  "pricing_method" varchar check("pricing_method" in('fixed', 'attributes_only', 'area')) not null,
  "base_price_fixed" integer not null default '0',
  "base_price_unit" integer not null default '0',
  "base_price_multiplier" integer not null default '0',
  "min_quantity" integer not null default '1',
  "quantity_step" integer not null default '1',
  "min_chargeable_area" integer not null default '0',
  "min_chargeable_price" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "requires_upload" tinyint(1) not null default '0',
  "upload_categories" text,
  "standard_roll_sizes" text,
  "max_print_width" integer,
  "max_print_length" integer,
  "bleed_margin" integer not null default '0',
  "safe_zone" integer not null default '0',
  "thickness_factor" numeric not null default '0',
  "base_production_days" integer not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "image" varchar,
  "upload_rules" text,
  "is_market_sensitive" tinyint(1) not null default '0',
  "market_notice" varchar,
  foreign key("printing_product_type_id") references "printing_product_types"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "printing_attributes"(
  "id" integer primary key autoincrement not null,
  "printing_type_id" integer not null,
  "name" varchar not null,
  "label" varchar not null,
  "type" varchar check("type" in('select', 'radio', 'checkbox', 'number', 'multiplier_number')) not null,
  "step" varchar check("step" in('specifications', 'finishing')) not null,
  "is_required" tinyint(1) not null default '0',
  "sort_order" integer not null default '0',
  "min_value" integer,
  "max_value" integer,
  "conditional_rules" text,
  "linked_upload_category" varchar,
  "is_dimension_width" tinyint(1) not null default '0',
  "is_dimension_length" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("printing_type_id") references "printing_types"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "printing_attribute_values"(
  "id" integer primary key autoincrement not null,
  "printing_attribute_id" integer not null,
  "value" varchar not null,
  "label" varchar not null,
  "fixed_price" integer not null default '0',
  "unit_price" integer not null default '0',
  "multiplier_price" integer not null default '0',
  "weight" numeric not null default '0',
  "weight_type" varchar check("weight_type" in('per_unit', 'per_sqm')) not null default 'per_unit',
  "tiered_prices" text,
  "added_production_days" integer not null default '0',
  "required_upload_category" varchar,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  foreign key("printing_attribute_id") references "printing_attributes"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "printing_price_audit_log"(
  "id" integer primary key autoincrement not null,
  "auditable_type" varchar not null,
  "auditable_id" integer not null,
  "field_name" varchar not null,
  "old_value" text,
  "new_value" text,
  "changed_by" integer not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "printing_price_audit_log_auditable_type_auditable_id_index" on "printing_price_audit_log"(
  "auditable_type",
  "auditable_id"
);
CREATE TABLE IF NOT EXISTS "order_items"(
  "id" integer primary key autoincrement not null,
  "order_id" integer not null,
  "product_id" integer,
  "name" varchar not null,
  "quantity" integer not null,
  "price_at_purchase" numeric not null,
  "created_at" datetime,
  "updated_at" datetime,
  "variant_id" integer,
  "total" numeric not null default('0'),
  "item_type" varchar not null default 'standard',
  "printing_type_id" integer,
  "custom_product_name" varchar,
  "printing_options" text,
  "uploaded_files" text,
  "file_status" varchar not null default 'not_applicable',
  "file_rejection_reason" text,
  "estimated_delivery_date" date,
  "tax_basis_points_snapshot" integer not null default '0',
  "source_order_item_id" integer,
  "requires_price_confirmation" tinyint(1) not null default '0',
  "price_confirmation_status" varchar not null default 'not_applicable',
  "original_line_total" numeric,
  "confirmed_line_total" numeric,
  "line_difference" numeric,
  "admin_notes" varchar,
  foreign key("variant_id") references product_variants("id") on delete set null on update no action,
  foreign key("order_id") references orders("id") on delete cascade on update no action,
  foreign key("product_id") references products("id") on delete set null on update no action,
  foreign key("printing_type_id") references "printing_types"("id") on delete set null,
  foreign key("source_order_item_id") references "order_items"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "cart_items"(
  "id" integer primary key autoincrement not null,
  "cart_id" integer not null,
  "product_id" integer,
  "product_variant_id" integer,
  "quantity" integer not null default('1'),
  "price" numeric not null,
  "is_sale_item" tinyint(1) not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  "item_type" varchar not null default 'standard',
  "printing_type_id" integer,
  "custom_product_name" varchar,
  "printing_options" text,
  "uploaded_files" text,
  "file_status" varchar not null default 'not_applicable',
  "file_rejection_reason" text,
  "tax_basis_points_snapshot" integer not null default '0',
  "quote_expires_at" datetime,
  foreign key("product_variant_id") references product_variants("id") on delete set null on update no action,
  foreign key("cart_id") references carts("id") on delete cascade on update no action,
  foreign key("printing_type_id") references "printing_types"("id") on delete set null,
  foreign key("product_id") references "products"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "printing_volume_discounts"(
  "id" integer primary key autoincrement not null,
  "printing_type_id" integer not null,
  "min_qty" integer not null,
  "max_qty" integer,
  "discount_basis_points" integer not null,
  "added_production_days" integer not null default('0'),
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("printing_type_id") references printing_types("id") on delete cascade on update no action
);
CREATE UNIQUE INDEX "vol_discount_unique" on "printing_volume_discounts"(
  "printing_type_id",
  "min_qty",
  "max_qty"
);
CREATE TABLE IF NOT EXISTS "temporary_uploads"(
  "id" varchar not null,
  "session_id" varchar,
  "user_id" integer,
  "file_path" varchar not null,
  "category" varchar not null,
  "page_count" integer,
  "created_at" datetime,
  "updated_at" datetime,
  "disk" varchar not null default 'local',
  primary key("id")
);
CREATE INDEX "temporary_uploads_session_id_index" on "temporary_uploads"(
  "session_id"
);
CREATE INDEX "temporary_uploads_user_id_index" on "temporary_uploads"(
  "user_id"
);
CREATE TABLE IF NOT EXISTS "blog_posts"(
  "id" integer primary key autoincrement not null,
  "title" varchar not null,
  "slug" varchar not null,
  "category" varchar,
  "status" varchar check("status" in('published', 'draft')) not null default 'draft',
  "meta_title" varchar,
  "meta_description" text,
  "featured_image" varchar,
  "video" varchar,
  "content" text,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "meta_keywords" varchar,
  "canonical_url" varchar,
  "og_image" varchar,
  "is_indexable" tinyint(1) not null default '1',
  "schema_markup" text
);
CREATE UNIQUE INDEX "blog_posts_slug_unique" on "blog_posts"("slug");
CREATE TABLE IF NOT EXISTS "addon_groups"(
  "id" integer primary key autoincrement not null,
  "name" text not null,
  "description" text,
  "input_type" varchar check("input_type" in('checkbox', 'radio', 'select')) not null default 'checkbox',
  "price_multiplier_type" varchar check("price_multiplier_type" in('per_item', 'per_order_line')) not null default 'per_item',
  "is_required" tinyint(1) not null default '0',
  "is_active" tinyint(1) not null default '1',
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE TABLE IF NOT EXISTS "product_addons"(
  "id" integer primary key autoincrement not null,
  "addon_group_id" integer not null,
  "name" text not null,
  "description" text,
  "type" varchar check("type" in('simple', 'printing_linked')) not null default 'simple',
  "price" numeric not null default '0',
  "price_type" varchar check("price_type" in('fixed', 'percentage')) not null default 'fixed',
  "printing_type_id" integer,
  "requires_upload" tinyint(1) not null default '0',
  "track_inventory" tinyint(1) not null default '0',
  "quantity" integer not null default '0',
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "allow_multiple_uploads" tinyint(1) not null default '0',
  "max_uploads" integer default '1',
  foreign key("addon_group_id") references "addon_groups"("id") on delete cascade,
  foreign key("printing_type_id") references "printing_types"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "stock_reservations"(
  "id" integer primary key autoincrement not null,
  "product_addon_id" integer,
  "product_id" integer,
  "product_variant_id" integer,
  "order_id" integer,
  "session_id" varchar,
  "user_id" integer,
  "quantity" integer not null default '1',
  "expires_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("product_addon_id") references "product_addons"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete cascade,
  foreign key("product_variant_id") references "product_variants"("id") on delete cascade,
  foreign key("order_id") references "orders"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "stock_reservations_session_id_index" on "stock_reservations"(
  "session_id"
);
CREATE INDEX "stock_reservations_expires_at_index" on "stock_reservations"(
  "expires_at"
);
CREATE TABLE IF NOT EXISTS "addon_group_category"(
  "addon_group_id" integer not null,
  "category_id" integer not null,
  foreign key("addon_group_id") references "addon_groups"("id") on delete cascade,
  foreign key("category_id") references "categories"("id") on delete cascade,
  primary key("addon_group_id", "category_id")
);
CREATE TABLE IF NOT EXISTS "addon_group_product"(
  "addon_group_id" integer not null,
  "product_id" integer not null,
  foreign key("addon_group_id") references "addon_groups"("id") on delete cascade,
  foreign key("product_id") references "products"("id") on delete cascade,
  primary key("addon_group_id", "product_id")
);
CREATE TABLE IF NOT EXISTS "cart_item_addons"(
  "id" integer primary key autoincrement not null,
  "cart_item_id" integer not null,
  "product_addon_id" integer not null,
  "quantity" integer not null default '1',
  "options_snapshot" text,
  "created_at" datetime,
  "updated_at" datetime,
  "disk" varchar not null default 'local',
  "temp_file_ids" text,
  "file_paths" text,
  foreign key("cart_item_id") references "cart_items"("id") on delete cascade,
  foreign key("product_addon_id") references "product_addons"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "order_item_addons"(
  "id" integer primary key autoincrement not null,
  "order_item_id" integer not null,
  "product_addon_id" integer,
  "name_snapshot" text not null,
  "price_snapshot" numeric not null,
  "price_type_snapshot" varchar not null,
  "quantity" integer not null default '1',
  "options_snapshot" text,
  "created_at" datetime,
  "updated_at" datetime,
  "disk" varchar not null default 'local',
  "file_paths" text,
  foreign key("order_item_id") references "order_items"("id") on delete cascade,
  foreign key("product_addon_id") references "product_addons"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "offers"(
  "id" integer primary key autoincrement not null,
  "title" text not null,
  "description" text,
  "image_path" text not null,
  "linkable_type" varchar,
  "linkable_id" integer,
  "custom_url" varchar,
  "is_active" tinyint(1) not null default '1',
  "start_date" datetime,
  "end_date" datetime,
  "sort_order" integer not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime
);
CREATE INDEX "offers_linkable_type_linkable_id_index" on "offers"(
  "linkable_type",
  "linkable_id"
);
CREATE TABLE IF NOT EXISTS "customer_social_accounts"(
  "id" integer primary key autoincrement not null,
  "customer_id" varchar not null,
  "provider_name" varchar not null,
  "provider_id" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("customer_id") references "customers"("id") on delete cascade
);
CREATE INDEX "customer_social_accounts_customer_id_index" on "customer_social_accounts"(
  "customer_id"
);
CREATE UNIQUE INDEX "customer_social_accounts_provider_id_unique" on "customer_social_accounts"(
  "provider_id"
);
CREATE TABLE IF NOT EXISTS "notifications"(
  "id" varchar not null,
  "type" varchar not null,
  "notifiable_type" varchar not null,
  "notifiable_id" integer not null,
  "data" text not null,
  "read_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  primary key("id")
);
CREATE INDEX "notifications_notifiable_type_notifiable_id_index" on "notifications"(
  "notifiable_type",
  "notifiable_id"
);
CREATE TABLE IF NOT EXISTS "push_subscriptions"(
  "id" integer primary key autoincrement not null,
  "subscribable_type" varchar not null,
  "subscribable_id" integer not null,
  "endpoint" varchar not null,
  "public_key" varchar,
  "auth_token" varchar,
  "content_encoding" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "push_subscriptions_subscribable_morph_idx" on "push_subscriptions"(
  "subscribable_type",
  "subscribable_id"
);
CREATE UNIQUE INDEX "push_subscriptions_endpoint_unique" on "push_subscriptions"(
  "endpoint"
);
CREATE UNIQUE INDEX "categories_slug_unique" on "categories"("slug");
CREATE TABLE IF NOT EXISTS "coupons"(
  "id" integer primary key autoincrement not null,
  "code" varchar not null,
  "type" varchar check("type" in('percentage', 'fixed')) not null default 'percentage',
  "value" numeric not null,
  "min_order_amount" numeric,
  "max_discount_amount" numeric,
  "usage_limit" integer,
  "usage_per_user" integer not null default '1',
  "times_used" integer not null default '0',
  "apply_to_sale_items" tinyint(1) not null default '0',
  "start_date" datetime,
  "expires_at" datetime,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "coupons_code_index" on "coupons"("code");
CREATE INDEX "coupons_is_active_index" on "coupons"("is_active");
CREATE UNIQUE INDEX "coupons_code_unique" on "coupons"("code");
CREATE TABLE IF NOT EXISTS "coupon_usages"(
  "id" integer primary key autoincrement not null,
  "coupon_id" integer not null,
  "order_id" integer not null,
  "customer_id" integer,
  "customer_phone" varchar,
  "customer_email" varchar,
  "discount_amount" numeric not null,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("coupon_id") references "coupons"("id") on delete cascade,
  foreign key("order_id") references "orders"("id") on delete cascade,
  foreign key("customer_id") references "customers"("id") on delete set null
);
CREATE INDEX "coupon_usages_coupon_id_customer_phone_index" on "coupon_usages"(
  "coupon_id",
  "customer_phone"
);
CREATE INDEX "coupon_usages_coupon_id_customer_id_index" on "coupon_usages"(
  "coupon_id",
  "customer_id"
);
CREATE INDEX "coupon_usages_customer_phone_index" on "coupon_usages"(
  "customer_phone"
);
CREATE INDEX "coupon_usages_customer_email_index" on "coupon_usages"(
  "customer_email"
);
CREATE TABLE IF NOT EXISTS "orders"(
  "id" integer primary key autoincrement not null,
  "customer_name" varchar not null,
  "customer_phone" varchar not null,
  "total" numeric not null,
  "items_count" integer not null,
  "status" varchar not null default('needs_action'),
  "payment_status" varchar not null default('unpaid'),
  "shipping_company" varchar,
  "tracking_number" varchar,
  "shipping_address" text,
  "created_at" datetime,
  "updated_at" datetime,
  "deleted_at" datetime,
  "payment_method" varchar not null default('cod'),
  "zone_id" integer,
  "subtotal" numeric not null default('0'),
  "shipping_cost" numeric not null default('0'),
  "is_fallback_shipping" tinyint(1) not null default('0'),
  "latest_shipping_update_at" datetime,
  "cart_id" integer,
  "customer_id" varchar,
  "coupon_id" integer,
  "coupon_code" varchar,
  "discount_amount" numeric not null default '0',
  "shipping_method" varchar not null default 'standard',
  "cod_fee" numeric not null default '0',
  "tax_amount" numeric not null default '0',
  "payment_type" varchar not null default 'full',
  "deposit_percentage" numeric,
  "deposit_amount" numeric not null default '0',
  "remaining_amount" numeric not null default '0',
  "deposit_paid_at" datetime,
  "remaining_paid_at" datetime,
  "payment_token" varchar,
  "payment_token_expires_at" datetime,
  "price_confirmed_at" datetime,
  "price_expires_at" datetime,
  "payment_session_expires_at" datetime,
  "admin_price_notes" text,
  "inventory_deducted_at" datetime,
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("zone_id") references shipping_zones("id") on delete restrict on update no action,
  foreign key("cart_id") references carts("id") on delete set null on update no action,
  foreign key("coupon_id") references "coupons"("id") on delete set null
);
CREATE INDEX "orders_customer_name_index" on "orders"("customer_name");
CREATE INDEX "orders_customer_phone_index" on "orders"("customer_phone");
CREATE INDEX "orders_status_index" on "orders"("status");
CREATE TABLE IF NOT EXISTS "carts"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "store_id" integer not null,
  "session_id" varchar,
  "customer_id" varchar,
  "customer_name" varchar,
  "customer_email" varchar,
  "customer_phone" varchar,
  "customer_type" varchar not null default('anonymous'),
  "ip_address" varchar,
  "user_agent" varchar,
  "status" varchar not null default('active'),
  "total_amount" numeric not null default('0'),
  "has_push_permission" tinyint(1) not null default('0'),
  "abandoned_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "coupon_id" integer,
  "discount_amount" numeric not null default '0',
  foreign key("customer_id") references customers("id") on delete set null on update no action,
  foreign key("coupon_id") references "coupons"("id") on delete set null
);
CREATE INDEX "carts_session_id_index" on "carts"("session_id");
CREATE INDEX "carts_store_id_index" on "carts"("store_id");
CREATE UNIQUE INDEX "carts_uuid_unique" on "carts"("uuid");
CREATE UNIQUE INDEX "orders_payment_token_unique" on "orders"("payment_token");

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_07_18_171400_create_attributes_table',1);
INSERT INTO migrations VALUES(5,'2026_07_18_171400_create_categories_table',1);
INSERT INTO migrations VALUES(6,'2026_07_18_171401_create_products_table',1);
INSERT INTO migrations VALUES(7,'2026_07_18_171402_create_product_variants_table',1);
INSERT INTO migrations VALUES(8,'2026_07_18_171442_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(9,'2026_07_18_182253_add_images_to_products_table',1);
INSERT INTO migrations VALUES(10,'2026_07_18_184722_add_slug_to_products_table',1);
INSERT INTO migrations VALUES(11,'2026_07_19_001953_create_customers_table',1);
INSERT INTO migrations VALUES(12,'2026_07_19_002000_create_customer_browsing_histories_table',1);
INSERT INTO migrations VALUES(13,'2026_07_19_030226_create_orders_table',1);
INSERT INTO migrations VALUES(14,'2026_07_19_030227_create_order_items_table',1);
INSERT INTO migrations VALUES(15,'2026_07_19_030228_create_store_settings_table',1);
INSERT INTO migrations VALUES(16,'2026_07_21_025517_create_carts_table',1);
INSERT INTO migrations VALUES(17,'2026_07_21_025518_create_cart_discounts_table',1);
INSERT INTO migrations VALUES(18,'2026_07_21_025518_create_cart_items_table',1);
INSERT INTO migrations VALUES(19,'2026_07_21_025519_create_cart_contact_histories_table',1);
INSERT INTO migrations VALUES(20,'2026_07_21_025520_create_abandoned_carts_settings_table',1);
INSERT INTO migrations VALUES(21,'2026_07_21_143628_add_status_to_customers_table',1);
INSERT INTO migrations VALUES(22,'2026_07_21_180845_create_faq_questions_table',1);
INSERT INTO migrations VALUES(23,'2026_07_21_180845_create_faq_sections_table',1);
INSERT INTO migrations VALUES(24,'2026_07_21_180846_create_chat_settings_table',1);
INSERT INTO migrations VALUES(25,'2026_07_21_180847_create_chat_conversations_table',1);
INSERT INTO migrations VALUES(26,'2026_07_21_180848_create_chat_messages_table',1);
INSERT INTO migrations VALUES(27,'2026_07_22_151240_create_settings_table',1);
INSERT INTO migrations VALUES(28,'2026_07_22_151241_create_shipping_companies_table',1);
INSERT INTO migrations VALUES(29,'2026_07_22_151242_create_payment_gateways_table',1);
INSERT INTO migrations VALUES(30,'2026_07_22_151242_create_shipping_zones_table',1);
INSERT INTO migrations VALUES(31,'2026_07_22_174431_add_is_active_to_products_and_categories_tables',1);
INSERT INTO migrations VALUES(32,'2026_07_22_174735_add_checkout_fields_to_orders_table',1);
INSERT INTO migrations VALUES(33,'2026_07_22_174844_add_variant_id_to_order_items_table',1);
INSERT INTO migrations VALUES(34,'2026_07_22_175804_add_subtotal_and_shipping_cost_to_orders_table',1);
INSERT INTO migrations VALUES(35,'2026_07_23_132126_add_auth_fields_to_customers_table',1);
INSERT INTO migrations VALUES(36,'2026_07_23_132301_create_permission_tables',1);
INSERT INTO migrations VALUES(37,'2026_08_02_010000_create_printing_product_types_table',1);
INSERT INTO migrations VALUES(38,'2026_08_02_010001_create_printing_types_table',1);
INSERT INTO migrations VALUES(39,'2026_08_02_010002_create_printing_attributes_table',1);
INSERT INTO migrations VALUES(40,'2026_08_02_010003_create_printing_attribute_values_table',1);
INSERT INTO migrations VALUES(41,'2026_08_02_010004_create_printing_volume_discounts_table',1);
INSERT INTO migrations VALUES(42,'2026_08_02_010005_create_printing_price_audit_log_table',1);
INSERT INTO migrations VALUES(43,'2026_08_02_010006_add_printing_columns_to_order_items_table',1);
INSERT INTO migrations VALUES(44,'2026_08_02_010007_add_printing_columns_to_cart_items_table',1);
INSERT INTO migrations VALUES(45,'2026_08_03_125909_make_max_qty_nullable_in_printing_volume_discounts_table',1);
INSERT INTO migrations VALUES(46,'2026_08_05_172416_add_image_to_printing_types_table',1);
INSERT INTO migrations VALUES(47,'2026_08_05_183139_create_temporary_uploads_table',1);
INSERT INTO migrations VALUES(48,'2026_08_06_155240_create_blog_posts_table',1);
INSERT INTO migrations VALUES(49,'2026_08_06_160038_add_upload_rules_to_printing_types_table',1);
INSERT INTO migrations VALUES(50,'2026_08_08_025710_create_addon_groups_table',1);
INSERT INTO migrations VALUES(51,'2026_08_08_025711_create_product_addons_table',1);
INSERT INTO migrations VALUES(52,'2026_08_08_025712_create_stock_reservations_table',1);
INSERT INTO migrations VALUES(53,'2026_08_08_025713_create_addon_group_category_table',1);
INSERT INTO migrations VALUES(54,'2026_08_08_025713_create_addon_group_product_table',1);
INSERT INTO migrations VALUES(55,'2026_08_08_025714_create_cart_item_addons_table',1);
INSERT INTO migrations VALUES(56,'2026_08_08_025715_create_order_item_addons_table',1);
INSERT INTO migrations VALUES(57,'2026_08_09_141304_create_offers_table',2);
INSERT INTO migrations VALUES(58,'2026_08_09_143347_add_deleted_at_to_offers_table',3);
INSERT INTO migrations VALUES(59,'2026_08_10_015958_add_shipping_fields_to_orders_table',4);
INSERT INTO migrations VALUES(60,'2026_08_10_020006_add_file_fields_to_addon_tables',4);
INSERT INTO migrations VALUES(61,'2026_08_10_023440_drop_temp_file_id_from_order_item_addons',5);
INSERT INTO migrations VALUES(62,'2026_08_10_130618_add_cart_id_to_orders_table',6);
INSERT INTO migrations VALUES(63,'2026_08_18_173000_create_customer_social_accounts_table',7);
INSERT INTO migrations VALUES(64,'2026_08_19_201800_add_multiple_uploads_to_addons',8);
INSERT INTO migrations VALUES(66,'2026_08_20_145610_add_customer_id_to_orders_table',9);
INSERT INTO migrations VALUES(67,'2026_08_21_133718_create_notifications_table',10);
INSERT INTO migrations VALUES(68,'2026_08_21_133719_create_push_subscriptions_table',10);
INSERT INTO migrations VALUES(69,'2026_08_21_133719_increase_push_subscriptions_endpoint_length',10);
INSERT INTO migrations VALUES(70,'2026_08_27_190000_add_advanced_seo_geo_fields',11);
INSERT INTO migrations VALUES(71,'2026_10_20_144001_create_coupons_table',12);
INSERT INTO migrations VALUES(72,'2026_10_20_144002_create_coupon_usages_table',12);
INSERT INTO migrations VALUES(73,'2026_10_20_144003_add_coupon_columns_to_orders_table',12);
INSERT INTO migrations VALUES(74,'2026_10_20_144004_add_coupon_columns_to_carts_table',12);
INSERT INTO migrations VALUES(75,'2026_10_20_170000_add_visits_count_to_customers_table',13);
INSERT INTO migrations VALUES(76,'2026_10_20_200000_add_shipping_fields_to_orders_table',14);
INSERT INTO migrations VALUES(77,'2026_10_20_210000_update_shipping_zones_to_egypt_governorates',15);
INSERT INTO migrations VALUES(78,'2026_10_20_151847_add_whatsapp_fields_to_chat_settings_table',16);
INSERT INTO migrations VALUES(79,'2026_10_20_220000_add_deposit_fields_to_orders_table',17);
INSERT INTO migrations VALUES(80,'2026_10_20_230000_add_volatile_pricing_fields',18);
INSERT INTO migrations VALUES(81,'2026_10_21_030000_add_inventory_deducted_at_to_orders_table',19);
