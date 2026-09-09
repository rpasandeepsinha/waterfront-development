--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3
-- Dumped by pg_dump version 16.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: action_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.action_events (
    id bigint NOT NULL,
    batch_id character(36) NOT NULL,
    name character varying(255) NOT NULL,
    actionable_type character varying(255) NOT NULL,
    actionable_id bigint NOT NULL,
    target_type character varying(255) NOT NULL,
    target_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint,
    fields text NOT NULL,
    status character varying(25) DEFAULT 'running'::character varying NOT NULL,
    exception text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    original text,
    changes text,
    identity_uuid uuid,
    identity_metadata jsonb
);


--
-- Name: action_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.action_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: action_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.action_events_id_seq OWNED BY public.action_events.id;


--
-- Name: addon_providers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.addon_providers (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    driver character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    enabled boolean DEFAULT false NOT NULL,
    "default" boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: addon_providers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.addon_providers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: addon_providers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.addon_providers_id_seq OWNED BY public.addon_providers.id;


--
-- Name: addons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.addons (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    text text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: addons_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.addons_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: addons_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.addons_id_seq OWNED BY public.addons.id;


--
-- Name: affiliate_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.affiliate_customer (
    affiliate_id integer NOT NULL,
    customer_id integer NOT NULL
);


--
-- Name: affiliate_product_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.affiliate_product_group (
    id integer NOT NULL,
    product_group_id integer NOT NULL,
    affiliate_id integer NOT NULL,
    rate double precision
);


--
-- Name: affiliate_product_group_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.affiliate_product_group_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: affiliate_product_group_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.affiliate_product_group_id_seq OWNED BY public.affiliate_product_group.id;


--
-- Name: affiliate_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.affiliate_transactions (
    id integer NOT NULL,
    original_customer_id integer NOT NULL,
    affiliate_customer_id integer NOT NULL,
    original_invoice_id integer NOT NULL,
    affiliate_invoice_id integer NOT NULL,
    affiliate_id integer NOT NULL,
    original_amount integer NOT NULL,
    kickback_amount integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: affiliate_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.affiliate_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: affiliate_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.affiliate_transactions_id_seq OWNED BY public.affiliate_transactions.id;


--
-- Name: affiliates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.affiliates (
    id integer NOT NULL,
    customer_id integer NOT NULL,
    key character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: affiliates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.affiliates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: affiliates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.affiliates_id_seq OWNED BY public.affiliates.id;


--
-- Name: audits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audits (
    id integer NOT NULL,
    user_type character varying(255),
    user_id bigint,
    event character varying(255) NOT NULL,
    auditable_type character varying(255) NOT NULL,
    auditable_id bigint NOT NULL,
    old_values text,
    new_values text,
    url text,
    ip_address inet,
    user_agent character varying(255),
    tags character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    identity_uuid uuid,
    identity_metadata jsonb
);


--
-- Name: audits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audits_id_seq OWNED BY public.audits.id;


--
-- Name: invoices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invoices (
    id integer NOT NULL,
    customer_number integer,
    customer_id integer NOT NULL,
    customer_name character varying(383) NOT NULL,
    goods_services character varying(255),
    process_date boolean DEFAULT false NOT NULL,
    payment_term integer DEFAULT 14 NOT NULL,
    purchase_reference character varying(255),
    domain character varying(255),
    start_date date NOT NULL,
    end_date date NOT NULL,
    period integer NOT NULL,
    gross_price integer,
    net_price integer NOT NULL,
    vat_code character varying(255) DEFAULT 'VH'::character varying NOT NULL,
    ledger_code integer NOT NULL,
    sent_to_harbor_at timestamp(0) without time zone,
    paid boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    vat_rate numeric(4,2) NOT NULL,
    announced_by_harbor_at timestamp(0) without time zone,
    parent_invoice_id integer,
    credit_reason character varying(255),
    merge_on_pdf_with_invoice_id integer,
    title character varying(255) NOT NULL,
    description character varying(255) NOT NULL,
    group_label character varying(255),
    type character varying(50) NOT NULL,
    prepaid_reference character varying(255),
    product_id integer NOT NULL,
    subscription_id integer,
    CONSTRAINT invoices_credit_reason_check CHECK (((credit_reason)::text = ANY (ARRAY[('reason_wet_van_dam'::character varying)::text, ('reason_revocation'::character varying)::text, ('reason_dissatisfied'::character varying)::text, ('reason_cancellation'::character varying)::text, ('reason_cancellation_renewal'::character varying)::text, ('reason_failure'::character varying)::text, ('reason_downgrade'::character varying)::text, ('reason_other'::character varying)::text, ('reason_retention'::character varying)::text, ('reason_abuse'::character varying)::text])))
);


--
-- Name: migrated_subscription_subscription; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_subscription_subscription (
    subscription_id integer NOT NULL,
    migrated_subscription_id bigint NOT NULL
);


--
-- Name: migrated_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_subscriptions (
    id bigint NOT NULL,
    reference_subscription_id character varying(255),
    reference_product_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: product_groups; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_groups (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    ledger_code integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    default_rate double precision DEFAULT '0'::double precision NOT NULL,
    default_billing_period integer DEFAULT 12 NOT NULL,
    default_contract_period integer DEFAULT 12 NOT NULL
);


--
-- Name: product_prices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_prices (
    id integer NOT NULL,
    product_id integer NOT NULL,
    billing_period integer NOT NULL,
    type character varying(255) NOT NULL,
    regular_price integer NOT NULL,
    promotion_price integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    product_discount_id integer,
    contract_period integer NOT NULL,
    translation_key_id bigint,
    introduction_price integer,
    orderable boolean DEFAULT true NOT NULL,
    action_period integer,
    action_period_price integer,
    is_default boolean DEFAULT false NOT NULL,
    CONSTRAINT chk_contract_period_gte_billing_period CHECK ((contract_period >= billing_period)),
    CONSTRAINT chk_contract_period_multiple_of_billing_period CHECK ((mod(contract_period, billing_period) = 0)),
    CONSTRAINT chk_non_negative_promotion_price CHECK ((promotion_price >= 0)),
    CONSTRAINT chk_non_negative_regular_price CHECK ((regular_price >= 0)),
    CONSTRAINT chk_positive_billing_period CHECK ((billing_period > 0)),
    CONSTRAINT chk_positive_contract_period CHECK ((contract_period > 0))
);


--
-- Name: products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.products (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    product_group_id integer NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    orderable boolean NOT NULL,
    weight integer NOT NULL,
    external_offer_uuid character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    slug character varying(255) DEFAULT 'slug'::character varying NOT NULL,
    requirements_translation_id bigint,
    instant_active boolean DEFAULT false NOT NULL,
    should_notify_support boolean DEFAULT true NOT NULL
);


--
-- Name: COLUMN products.instant_active; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.products.instant_active IS 'Should the subscription be set to active automatically and send an email to the customer notifying them that the subscription is active';


--
-- Name: COLUMN products.should_notify_support; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.products.should_notify_support IS 'Should the system send an email to customer support notifying them about an order or a cancellation';


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    product_price_id integer,
    product_uuid character(36) NOT NULL,
    customer_id integer NOT NULL,
    domain character varying(255),
    technical_status character varying(20),
    administrative_status character varying(32) DEFAULT 'active'::character varying NOT NULL,
    contract_period integer NOT NULL,
    net_price integer NOT NULL,
    gross_price integer NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    parent_subscription_id integer,
    cancel_date date,
    billing_period integer NOT NULL,
    next_billing_date date NOT NULL,
    suspended_at timestamp(0) without time zone,
    termination_date date,
    cancel_reason character varying(255)
);


--
-- Name: cancellation_individual_subscription; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.cancellation_individual_subscription AS
 SELECT s.id AS subscription_id,
    s.customer_id,
    s.start_date AS started_at,
    s.cancel_date AS canceled_at,
    s.end_date,
        CASE
            WHEN (mss.migrated_subscription_id IS NOT NULL) THEN 'yes'::text
            WHEN (mss.migrated_subscription_id IS NULL) THEN 'no'::text
            ELSE NULL::text
        END AS is_migrated,
    ms.created_at AS migrated_at,
    pg.slug AS product_group_slug,
    p.slug AS product_slug,
    s.net_price AS current_net_price,
    ( SELECT string_agg(DISTINCT (ppi.net_price)::text, ','::text) AS previously_paid_net_prices
           FROM public.invoices ppi
          WHERE (ppi.subscription_id = s.id)) AS previously_paid_net_prices,
    pp.updated_at AS product_price_last_updated_at
   FROM (((((public.subscriptions s
     LEFT JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
     LEFT JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
     JOIN public.products p ON ((s.product_uuid = p.uuid)))
     JOIN public.product_groups pg ON ((p.product_group_id = pg.id)))
     LEFT JOIN public.product_prices pp ON ((s.product_price_id = pp.id)))
  WHERE (((s.administrative_status)::text = 'canceled'::text) AND (s.end_date > CURRENT_DATE))
  GROUP BY s.id, s.customer_id, s.start_date, s.cancel_date, s.end_date, ms.created_at, pg.slug, p.slug, s.net_price, pp.updated_at, mss.migrated_subscription_id
  ORDER BY s.id DESC;


--
-- Name: cancellation_state_over_time; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.cancellation_state_over_time AS
 SELECT EXTRACT(year FROM s.end_date) AS year,
    EXTRACT(month FROM s.end_date) AS month,
    count(s.id) AS amount_of_subscriptions,
    sum(s.net_price) AS total_in_cents
   FROM public.subscriptions s
  WHERE (((s.administrative_status)::text = 'canceled'::text) AND (s.end_date > CURRENT_DATE))
  GROUP BY (EXTRACT(year FROM s.end_date)), (EXTRACT(month FROM s.end_date))
  ORDER BY (EXTRACT(year FROM s.end_date)) DESC, (EXTRACT(month FROM s.end_date)) DESC;


--
-- Name: cloudstack_environment_products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_environment_products (
    id integer NOT NULL,
    environment_id integer NOT NULL,
    product_id integer NOT NULL,
    product_identifier character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack__environment_products_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack__environment_products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack__environment_products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack__environment_products_id_seq OWNED BY public.cloudstack_environment_products.id;


--
-- Name: cloudstack_environments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_environments (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    api_url character varying(255) NOT NULL,
    domain_id character varying(255) NOT NULL,
    default_email_address character varying(255) NOT NULL,
    default_role_id character varying(255) NOT NULL,
    last_processed_event_id character varying(255),
    last_processed_event_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    preferred boolean NOT NULL,
    domain_name character varying(255) NOT NULL,
    ui_url character varying(255) NOT NULL,
    api_key text,
    secret_key text
);


--
-- Name: cloudstack__environments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack__environments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack__environments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack__environments_id_seq OWNED BY public.cloudstack_environments.id;


--
-- Name: cloudstack_managerdomain_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_managerdomain_subscriptions (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    environment_id integer NOT NULL,
    domain_id character varying(255),
    domain_name character varying(255) NOT NULL,
    account character varying(255) NOT NULL,
    username character varying(255) NOT NULL,
    last_subscription_sync_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack__managerdomain_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack__managerdomain_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack__managerdomain_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack__managerdomain_subscriptions_id_seq OWNED BY public.cloudstack_managerdomain_subscriptions.id;


--
-- Name: cloudstack_vm_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_vm_subscriptions (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    manager_domain_subscription_id integer NOT NULL,
    cloudstack_id character varying(255),
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_result text,
    last_result_received timestamp(0) without time zone,
    last_action_status character varying(255),
    custom_name character varying(255)
);


--
-- Name: cloudstack__vm_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack__vm_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack__vm_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack__vm_subscriptions_id_seq OWNED BY public.cloudstack_vm_subscriptions.id;


--
-- Name: cloudstack_volume_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_volume_subscriptions (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    manager_domain_subscription_id integer NOT NULL,
    cloudstack_id character varying(255) NOT NULL,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack__volume_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack__volume_subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack__volume_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack__volume_subscriptions_id_seq OWNED BY public.cloudstack_volume_subscriptions.id;


--
-- Name: cloudstack_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_jobs (
    id bigint NOT NULL,
    vm_subscription_id integer,
    job_id character(36) NOT NULL,
    user_id character(36),
    account_id character(36),
    cmd character varying(255),
    status integer,
    proc_status integer,
    result_code integer,
    result_type character varying(255),
    instance_type character varying(255),
    instance_id character(36),
    cloudstack_created timestamp(0) with time zone,
    cloudstack_completed timestamp(0) with time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack_jobs_id_seq OWNED BY public.cloudstack_jobs.id;


--
-- Name: cloudstack_managerdomain_cloudstack_vm_ssh_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_managerdomain_cloudstack_vm_ssh_keys (
    ssh_key_id bigint NOT NULL,
    manager_domain_deployment_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack_vm_deployment_ssh_key; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_vm_deployment_ssh_key (
    vm_deployment_id integer NOT NULL,
    ssh_key_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack_vm_ssh_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cloudstack_vm_ssh_keys (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    customer_id integer NOT NULL,
    key_name character varying(255) NOT NULL,
    public_key text NOT NULL,
    fingerprint character varying(255) NOT NULL,
    cloudstack_ssh_name character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cloudstack_vm_ssh_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cloudstack_vm_ssh_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cloudstack_vm_ssh_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cloudstack_vm_ssh_keys_id_seq OWNED BY public.cloudstack_vm_ssh_keys.id;


--
-- Name: customer_addresses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_addresses (
    id integer NOT NULL,
    customer_id integer NOT NULL,
    street_name character varying(255) NOT NULL,
    street_number character varying(255) NOT NULL,
    zip_code character varying(255) NOT NULL,
    city character varying(255) NOT NULL,
    province character varying(255),
    country_code character(2) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    street_number_addition character varying(255)
);


--
-- Name: customer_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_addresses_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_addresses_id_seq OWNED BY public.customer_addresses.id;


--
-- Name: customer_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_contacts (
    id integer NOT NULL,
    customer_id integer NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    uuid character(36) NOT NULL,
    type character varying(255) DEFAULT 'default'::character varying NOT NULL
);


--
-- Name: customer_contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_contacts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_contacts_id_seq OWNED BY public.customer_contacts.id;


--
-- Name: customer_customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_customers (
    parent_customer_id integer NOT NULL,
    child_customer_id integer NOT NULL
);


--
-- Name: customer_migrated_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_migrated_customer (
    customer_id integer NOT NULL,
    migrated_customer_id bigint NOT NULL
);


--
-- Name: customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customers (
    id integer NOT NULL,
    customer_number integer NOT NULL,
    uuid character(36) NOT NULL,
    partner_id integer,
    organization character varying(255),
    is_partner boolean,
    department character varying(255),
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    gender character varying(255) NOT NULL,
    phone_country_code character varying(255) NOT NULL,
    phone_area_code character varying(255) NOT NULL,
    phone_subscriber_number character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    locale character varying(255) NOT NULL,
    terms_accepted boolean DEFAULT false NOT NULL,
    icp boolean DEFAULT false NOT NULL,
    send_reminder_before_renewal boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    coc_number character varying(255),
    vat_number character varying(255),
    purchase_reference character varying(255),
    invoice_history_url character varying(255),
    terms_of_payment character varying(255) DEFAULT '14'::character varying NOT NULL,
    credit_limit integer DEFAULT 50000 NOT NULL,
    vat_rate numeric(4,2),
    anonymized_at timestamp(0) without time zone,
    admin_url character varying(255),
    payment_type character varying(10) DEFAULT 'direct'::character varying NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    data_last_confirmed_at timestamp(0) without time zone,
    has_direct_debit boolean DEFAULT false NOT NULL,
    is_abuse boolean DEFAULT false NOT NULL,
    CONSTRAINT chk_non_negative_credit_limit CHECK ((credit_limit >= 0))
);


--
-- Name: customer_number_increment; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_number_increment
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_number_increment; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_number_increment OWNED BY public.customers.customer_number;


--
-- Name: customer_product_discount; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_product_discount (
    id integer NOT NULL,
    customer_id integer NOT NULL,
    product_discount_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_product_discount_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_product_discount_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_product_discount_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_product_discount_id_seq OWNED BY public.customer_product_discount.id;


--
-- Name: customer_product_group; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_product_group (
    customer_id integer NOT NULL,
    product_group_id integer NOT NULL,
    discount integer NOT NULL
);


--
-- Name: customer_vat_errors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_vat_errors (
    id bigint NOT NULL,
    customer_id integer NOT NULL,
    message character varying(255) NOT NULL,
    status_code integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_vat_errors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_vat_errors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_vat_errors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_vat_errors_id_seq OWNED BY public.customer_vat_errors.id;


--
-- Name: customer_wallets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customer_wallets (
    id integer NOT NULL,
    customer_id integer NOT NULL,
    amount integer NOT NULL,
    bank_account_number character varying(255),
    bank_account_name character varying(255),
    refund_requested_at timestamp(0) without time zone,
    csv_downloaded_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: customer_wallets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customer_wallets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customer_wallets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customer_wallets_id_seq OWNED BY public.customer_wallets.id;


--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customers_id_seq OWNED BY public.customers.id;


--
-- Name: dns_customer_template_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_customer_template_records (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    content character varying(1000) NOT NULL,
    type character varying(32) NOT NULL,
    ttl integer NOT NULL,
    priority integer,
    weight integer,
    port integer,
    disabled boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: dns__customer_template_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__customer_template_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__customer_template_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__customer_template_records_id_seq OWNED BY public.dns_customer_template_records.id;


--
-- Name: dns_customer_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_customer_templates (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    customer_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: dns__customer_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__customer_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__customer_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__customer_templates_id_seq OWNED BY public.dns_customer_templates.id;


--
-- Name: dns_nameservers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_nameservers (
    id bigint NOT NULL,
    dns_region_id bigint NOT NULL,
    nameserver character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: dns__nameservers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__nameservers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__nameservers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__nameservers_id_seq OWNED BY public.dns_nameservers.id;


--
-- Name: dns_regions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_regions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: dns__regions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__regions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__regions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__regions_id_seq OWNED BY public.dns_regions.id;


--
-- Name: dns_template_record_set_rows; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_template_record_set_rows (
    id bigint NOT NULL,
    template_record_set_id bigint NOT NULL,
    content character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns__template_record_set_rows_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__template_record_set_rows_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__template_record_set_rows_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__template_record_set_rows_id_seq OWNED BY public.dns_template_record_set_rows.id;


--
-- Name: dns_template_record_sets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_template_record_sets (
    id bigint NOT NULL,
    template_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(32) NOT NULL,
    ttl integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns__template_record_sets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__template_record_sets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__template_record_sets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__template_record_sets_id_seq OWNED BY public.dns_template_record_sets.id;


--
-- Name: dns_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_templates (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns__templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns__templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns__templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns__templates_id_seq OWNED BY public.dns_templates.id;


--
-- Name: dns_deployment_dns_nameserver; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_deployment_dns_nameserver (
    dns_nameserver_id integer NOT NULL,
    dns_deployment_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns_deployment_dns_vanity_nameserver; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_deployment_dns_vanity_nameserver (
    dns_deployment_id bigint NOT NULL,
    dns_vanity_nameserver_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns_deployments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_deployments (
    id bigint NOT NULL,
    subscription_uuid character(36) NOT NULL,
    last_result_received timestamp(0) without time zone,
    last_result json,
    last_result_premium_provider_received timestamp(0) without time zone,
    last_result_premium_provider json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    nameserver_type character varying(255) NOT NULL
);


--
-- Name: dns_deployments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns_deployments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns_deployments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns_deployments_id_seq OWNED BY public.dns_deployments.id;


--
-- Name: dns_external_nameservers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_external_nameservers (
    id bigint NOT NULL,
    dns_deployment_id bigint NOT NULL,
    nameserver character varying(255) NOT NULL,
    ipv4 character varying(255),
    ipv6 character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns_external_nameservers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns_external_nameservers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns_external_nameservers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns_external_nameservers_id_seq OWNED BY public.dns_external_nameservers.id;


--
-- Name: dns_nameserver_domain_subscription; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_nameserver_domain_subscription (
    dns_nameserver_id bigint NOT NULL,
    domain_subscription_id integer NOT NULL
);


--
-- Name: dns_record_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_record_changes (
    id bigint NOT NULL,
    record_type character varying(255) NOT NULL,
    change_type character varying(255) NOT NULL,
    agent_type character varying(255) NOT NULL,
    name text NOT NULL,
    content text NOT NULL,
    ttl integer NOT NULL,
    priority integer,
    weight integer,
    port integer,
    changed_by_uuid uuid,
    changed_by_metadata jsonb,
    subscription_id bigint NOT NULL,
    ip_address inet NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT dns_record_changes_agent_type_check CHECK (((agent_type)::text = ANY ((ARRAY['customer'::character varying, 'customer_support_agent'::character varying, 'system_process'::character varying])::text[]))),
    CONSTRAINT dns_record_changes_change_type_check CHECK (((change_type)::text = ANY ((ARRAY['deleted'::character varying, 'created'::character varying])::text[]))),
    CONSTRAINT dns_record_changes_record_type_check CHECK (((record_type)::text = ANY ((ARRAY['A'::character varying, 'AAAA'::character varying, 'ALIAS'::character varying, 'CAA'::character varying, 'CDS'::character varying, 'CNAME'::character varying, 'DNAME'::character varying, 'DS'::character varying, 'KEY'::character varying, 'LOC'::character varying, 'MX'::character varying, 'NAPTR'::character varying, 'NS'::character varying, 'OPENPGPKEY'::character varying, 'PTR'::character varying, 'RP'::character varying, 'SPF'::character varying, 'SRV'::character varying, 'SSHFP'::character varying, 'TLSA'::character varying, 'TXT'::character varying, 'WKS'::character varying, 'DNSKEY'::character varying, 'NSEC'::character varying, 'NSEC3'::character varying, 'NSEC3PARAM'::character varying, 'RRSIG'::character varying, 'URI'::character varying])::text[])))
);


--
-- Name: dns_record_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns_record_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns_record_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns_record_changes_id_seq OWNED BY public.dns_record_changes.id;


--
-- Name: dns_vanity_nameservers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dns_vanity_nameservers (
    id bigint NOT NULL,
    nameserver character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: dns_vanity_nameservers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dns_vanity_nameservers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dns_vanity_nameservers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dns_vanity_nameservers_id_seq OWNED BY public.dns_vanity_nameservers.id;


--
-- Name: domain_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_contacts (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    email character varying(255) NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    phone_country_code character varying(255) NOT NULL,
    phone_area_code character varying(255) NOT NULL,
    phone_subscriber_number character varying(255) NOT NULL,
    street_name character varying(255) NOT NULL,
    street_number character varying(255) NOT NULL,
    zip_code character varying(255) NOT NULL,
    city character varying(255) NOT NULL,
    country_code character varying(255) NOT NULL,
    organization character varying(255),
    default_owner boolean DEFAULT false NOT NULL,
    customer_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: domain__contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain__contacts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain__contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain__contacts_id_seq OWNED BY public.domain_contacts.id;


--
-- Name: domain_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_subscriptions (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    provider_id integer NOT NULL,
    last_result text,
    last_result_received timestamp(0) without time zone,
    template_id bigint,
    contact_owner_id integer,
    dnssec_enabled boolean DEFAULT false NOT NULL,
    private_whois_enabled boolean DEFAULT false NOT NULL,
    transfer_secret character varying(255)
);


--
-- Name: domain__subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain__subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain__subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain__subscriptions_id_seq OWNED BY public.domain_subscriptions.id;


--
-- Name: domain_contact_anonymous_handles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_contact_anonymous_handles (
    id bigint NOT NULL,
    handle character varying(255) NOT NULL,
    original_business_unit character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: domain_contact_anonymous_handles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain_contact_anonymous_handles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain_contact_anonymous_handles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain_contact_anonymous_handles_id_seq OWNED BY public.domain_contact_anonymous_handles.id;


--
-- Name: domain_contact_provider; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_contact_provider (
    id integer NOT NULL,
    external_contact character varying(255) NOT NULL,
    domain_contact_id integer NOT NULL,
    provider_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: domain_contact_domain_provider_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain_contact_domain_provider_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain_contact_domain_provider_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain_contact_domain_provider_id_seq OWNED BY public.domain_contact_provider.id;


--
-- Name: domain_provider_status; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.domain_provider_status (
    id bigint NOT NULL,
    rtr_response_log_id bigint NOT NULL,
    provider_id integer NOT NULL,
    domain_subscription_id integer,
    status character varying(255) NOT NULL,
    received_result text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: domain_provider_status_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.domain_provider_status_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: domain_provider_status_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.domain_provider_status_id_seq OWNED BY public.domain_provider_status.id;


--
-- Name: email_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_history (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    template_id integer,
    receiver_type character varying(255) NOT NULL,
    receiver_uuid character varying(255) NOT NULL,
    cc_emails character varying(255),
    sent_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    receiver_email character varying(255),
    payload jsonb,
    hubspot_status character varying(255),
    hubspot_id character varying(255),
    requested_at timestamp(0) without time zone,
    last_result jsonb
);


--
-- Name: email_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_history_id_seq OWNED BY public.email_history.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: hosting_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hosting_servers (
    id bigint NOT NULL,
    customer_uuid character varying(36),
    hostname character varying(255) NOT NULL,
    ipv4 character varying(255),
    ipv6 character varying(255),
    plesk_version character varying(255),
    owner character varying(255),
    customer_login_as_admin boolean DEFAULT false NOT NULL,
    allow_new_websites boolean DEFAULT true NOT NULL,
    maximum_websites integer,
    secret_key text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    php_version character varying(32) DEFAULT 'plesk-php71-fastcgi'::character varying NOT NULL,
    type character varying(255) DEFAULT 'plesk'::character varying NOT NULL,
    name character varying(255),
    password text,
    username character varying(255),
    domain character varying(255),
    port character varying(255),
    loginkey text,
    use_ssl boolean
);


--
-- Name: hosting__servers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hosting__servers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hosting__servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hosting__servers_id_seq OWNED BY public.hosting_servers.id;


--
-- Name: hosting_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hosting_subscriptions (
    id integer NOT NULL,
    server_id bigint,
    subscription_uuid character(36) NOT NULL,
    provider_id integer,
    plesk_customer_username character varying(255),
    plesk_customer_id integer,
    memory integer,
    disk_space integer,
    email_addresses integer,
    traffic integer,
    databases integer,
    domains integer,
    permissions json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    directadmin_customer_username character varying(255),
    ftps_host character varying(255),
    last_created_result text,
    last_created_result_received timestamp(0) without time zone,
    mail_only_provider_id integer,
    sitebuilder_provider_id integer,
    basekit_user_ref integer,
    basekit_site_ref integer,
    basekit_server_id bigint,
    mail_only_server_id bigint,
    has_valid_sso_call boolean DEFAULT false NOT NULL,
    wp_installation_id integer,
    spam_experts_cluster_id bigint
);


--
-- Name: hosting__subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hosting__subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hosting__subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hosting__subscriptions_id_seq OWNED BY public.hosting_subscriptions.id;


--
-- Name: subscription_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_changes (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    subscription_uuid character(36) NOT NULL,
    from_product_uuid character(36) NOT NULL,
    to_product_uuid character(36) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    requested_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone
);


--
-- Name: hosting__upgrades_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hosting__upgrades_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hosting__upgrades_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hosting__upgrades_id_seq OWNED BY public.subscription_changes.id;


--
-- Name: hosting_redirecting_legacy_servers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hosting_redirecting_legacy_servers (
    id integer NOT NULL,
    hostname character varying(255) NOT NULL,
    ipv4 character varying(255) NOT NULL,
    ipv6 character varying(255),
    original_business_unit character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: hosting_forwarding_legacy_servers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hosting_forwarding_legacy_servers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hosting_forwarding_legacy_servers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hosting_forwarding_legacy_servers_id_seq OWNED BY public.hosting_redirecting_legacy_servers.id;


--
-- Name: hosting_product_compositions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hosting_product_compositions (
    id bigint NOT NULL,
    composed_product_id integer NOT NULL,
    mail_only_product_id integer NOT NULL,
    web_only_product_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    wp_composed_product_id integer,
    wp_web_only_product_id integer
);


--
-- Name: hosting_product_compositions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hosting_product_compositions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hosting_product_compositions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hosting_product_compositions_id_seq OWNED BY public.hosting_product_compositions.id;


--
-- Name: hubspot_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hubspot_events (
    id bigint NOT NULL,
    customer_id integer NOT NULL,
    status character varying(255) NOT NULL,
    event character varying(255) NOT NULL,
    message character varying(4096) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    latest boolean NOT NULL,
    hubspot_object_uuid uuid
);


--
-- Name: hubspot_events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hubspot_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hubspot_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hubspot_events_id_seq OWNED BY public.hubspot_events.id;


--
-- Name: invoices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invoices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invoices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255),
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: label_subscription; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.label_subscription (
    label_id bigint NOT NULL,
    subscription_id integer NOT NULL
);


--
-- Name: labels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.labels (
    id bigint NOT NULL,
    value text NOT NULL,
    customer_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: labels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.labels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: labels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.labels_id_seq OWNED BY public.labels.id;


--
-- Name: translation_languages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_languages (
    id bigint NOT NULL,
    locale character varying(255) NOT NULL,
    display_name character varying(255) NOT NULL,
    active boolean NOT NULL,
    "default" boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: languages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.languages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: languages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.languages_id_seq OWNED BY public.translation_languages.id;


--
-- Name: mandate_migrated_customer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mandate_migrated_customer (
    mandate_id bigint NOT NULL,
    migrated_customer_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: mandates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mandates (
    id bigint NOT NULL,
    mollie_mandate_reference_id character varying(255),
    method character varying(255) NOT NULL,
    signature_date date NOT NULL,
    mollie_customer_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    payt_mandate_reference_id character varying(255),
    CONSTRAINT mandates_method_check CHECK (((method)::text = ANY ((ARRAY['directdebit'::character varying, 'creditcard'::character varying, 'paypal'::character varying])::text[])))
);


--
-- Name: mandates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mandates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mandates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mandates_id_seq OWNED BY public.mandates.id;


--
-- Name: microsoft365_customer_info; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.microsoft365_customer_info (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    customer_id integer NOT NULL,
    kpn_customer_id character varying(255),
    technical_status character varying(255),
    tenant_name character varying(255),
    tenant_id character varying(255),
    tenant_access_verified boolean DEFAULT false NOT NULL,
    synced_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    type character varying(255) NOT NULL
);


--
-- Name: microsoft365_http_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.microsoft365_http_log (
    id integer NOT NULL,
    kpn_customer_id character varying(255),
    kpn_order_id character varying(255),
    tenant_name character varying(255),
    partner_reference character varying(255),
    log text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    xml_root_name character varying(255),
    subscription_id integer
);


--
-- Name: microsoft365_http_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.microsoft365_http_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: microsoft365_http_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.microsoft365_http_log_id_seq OWNED BY public.microsoft365_http_log.id;


--
-- Name: microsoft365_kpn_product; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.microsoft365_kpn_product (
    id bigint NOT NULL,
    product_price_id integer NOT NULL,
    kpn_product_code character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: microsoft365_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.microsoft365_subscriptions (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    subscription_id integer NOT NULL,
    kpn_order_id integer,
    kpn_status character varying(255) NOT NULL,
    microsoft365_customer_info_id bigint NOT NULL,
    kpn_start_date timestamp(0) without time zone
);


--
-- Name: microsoft365_sync_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.microsoft365_sync_log (
    id bigint NOT NULL,
    log text NOT NULL,
    microsoft365_customer_info_id bigint,
    microsoft365_subscription_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: microsoft365_sync_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.microsoft365_sync_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: microsoft365_sync_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.microsoft365_sync_log_id_seq OWNED BY public.microsoft365_sync_log.id;


--
-- Name: migrated_customer_migrated_subscription; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_customer_migrated_subscription (
    migrated_customer_id bigint NOT NULL,
    migrated_subscription_id bigint NOT NULL
);


--
-- Name: migrated_customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_customers (
    id bigint NOT NULL,
    reference_customer_number character varying(255) NOT NULL,
    reference_name character varying(255) NOT NULL,
    group_type character varying(255) NOT NULL,
    successful boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    administrative_successful boolean DEFAULT false NOT NULL,
    technical_successful boolean DEFAULT false NOT NULL,
    billing_successful boolean DEFAULT false NOT NULL,
    migrated_at timestamp(0) without time zone,
    dns_successful boolean DEFAULT false NOT NULL,
    enable_invoicing boolean DEFAULT false NOT NULL
);


--
-- Name: migrated_customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrated_customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrated_customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrated_customers_id_seq OWNED BY public.migrated_customers.id;


--
-- Name: migrated_dns_template_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_dns_template_records (
    id bigint NOT NULL,
    reference_record_id character varying(255) NOT NULL,
    migrated_dns_template_id bigint NOT NULL,
    dns_customer_template_record_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrated_dns_template_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrated_dns_template_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrated_dns_template_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrated_dns_template_records_id_seq OWNED BY public.migrated_dns_template_records.id;


--
-- Name: migrated_dns_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_dns_templates (
    id bigint NOT NULL,
    reference_template_id character varying(255) NOT NULL,
    migrated_customer_id bigint NOT NULL,
    dns_customer_template_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrated_dns_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrated_dns_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrated_dns_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrated_dns_templates_id_seq OWNED BY public.migrated_dns_templates.id;


--
-- Name: migrated_subscription_steps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrated_subscription_steps (
    id bigint NOT NULL,
    subscription_id integer NOT NULL,
    step character varying(255) NOT NULL,
    status character varying(255) NOT NULL,
    executed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: migrated_subscription_steps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrated_subscription_steps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrated_subscription_steps_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrated_subscription_steps_id_seq OWNED BY public.migrated_subscription_steps.id;


--
-- Name: migrated_subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrated_subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrated_subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrated_subscriptions_id_seq OWNED BY public.migrated_subscriptions.id;


--
-- Name: migration_invoicing_state; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.migration_invoicing_state AS
 SELECT base.batch_group,
        CASE
            WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
            WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
            ELSE NULL::text
        END AS invoicing_state,
    count(base.invoice_line_id) AS invoice_item_count,
    sum(base.invoice_line_net_price) AS net_sum_in_cents
   FROM ( SELECT mc.group_type AS batch_group,
            ms.reference_subscription_id AS legacy_subscription_id,
            inv.id AS invoice_line_id,
            inv.net_price AS invoice_line_net_price,
            inv.sent_to_harbor_at
           FROM (((((((((((((public.invoices inv
             JOIN public.subscriptions s ON ((s.id = inv.subscription_id)))
             JOIN public.products p ON ((s.product_uuid = p.uuid)))
             JOIN public.product_groups pg ON ((p.product_group_id = pg.id)))
             JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
             JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
             JOIN public.migrated_customer_migrated_subscription mcms ON ((ms.id = mcms.migrated_subscription_id)))
             JOIN public.migrated_customers mc ON ((mcms.migrated_customer_id = mc.id)))
             JOIN public.customers c ON ((s.customer_id = c.id)))
             JOIN public.customer_addresses ca ON ((c.id = ca.customer_id)))
             LEFT JOIN public.customer_contacts cc ON ((c.id = cc.customer_id)))
             LEFT JOIN public.customer_wallets cw ON ((c.id = cw.customer_id)))
             LEFT JOIN public.domain_subscriptions ds ON ((s.uuid = ds.subscription_uuid)))
             LEFT JOIN public.domain_contacts dc ON (((c.id = dc.customer_id) AND (ds.contact_owner_id = dc.id))))
          GROUP BY mc.group_type, ms.reference_subscription_id, inv.id, inv.net_price, inv.sent_to_harbor_at) base
  GROUP BY base.batch_group,
        CASE
            WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
            WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
            ELSE NULL::text
        END
  ORDER BY base.batch_group,
        CASE
            WHEN (base.sent_to_harbor_at IS NOT NULL) THEN 'successfully propagated'::text
            WHEN (base.sent_to_harbor_at IS NULL) THEN 'stuck in waterfront'::text
            ELSE NULL::text
        END;


--
-- Name: providers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.providers (
    id integer NOT NULL,
    type character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    enabled boolean NOT NULL,
    "default" boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ssl_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ssl_subscriptions (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    certificate_id character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    provider_id integer NOT NULL,
    webhook_request text,
    webhook_request_received timestamp(0) without time zone,
    last_result text,
    last_result_received timestamp(0) without time zone,
    custom_csr boolean DEFAULT false NOT NULL,
    has_reissued boolean DEFAULT false NOT NULL,
    request_id character varying(255)
);


--
-- Name: migration_state; Type: VIEW; Schema: public; Owner: -
--

CREATE VIEW public.migration_state AS
 SELECT row_number() OVER () AS id,
    mc.reference_customer_number AS external_customer_id,
    c.id AS waterfront_customer_id,
    c.customer_number AS waterfront_customer_number,
    mc.enable_invoicing AS enabled_invoicing,
    mc.group_type AS batch_group,
    mc.reference_name,
    c.email,
    c.first_name,
    c.last_name,
    c.organization,
    c.coc_number,
    c.vat_number,
    c.vat_rate,
    ca.country_code,
    c.phone_country_code,
    c.phone_area_code,
    c.phone_subscriber_number,
    cw.id AS wallet_id,
    cw.amount AS wallet_amount,
    cw.refund_requested_at,
    ms.reference_subscription_id AS legacy_subscription_id,
    s.id AS subscription_id,
    s.domain,
    pg.slug AS product_group,
    p.slug AS product,
    s.gross_price,
    s.net_price,
    s.administrative_status,
    s.technical_status,
    s.updated_at AS subscription_last_updated,
    s.end_date AS renewal_date,
    s.cancel_date,
    s.next_billing_date,
        CASE
            WHEN (dp.slug IS NOT NULL) THEN dp.slug
            WHEN (hph.slug IS NOT NULL) THEN hph.slug
            WHEN (hpm.slug IS NOT NULL) THEN hpm.slug
            WHEN (hps.slug IS NOT NULL) THEN hps.slug
            WHEN (sp.slug IS NOT NULL) THEN sp.slug
            ELSE NULL::character varying
        END AS technical_driver,
        CASE
            WHEN (hph.slug IS NOT NULL) THEN hser.hostname
            WHEN (hpm.slug IS NOT NULL) THEN hsm.hostname
            WHEN (hps.slug IS NOT NULL) THEN hsb.hostname
            ELSE NULL::character varying
        END AS server_hostname,
    dcdp.external_contact AS domain_contact_handle_id,
    ( SELECT string_agg((dn.nameserver)::text, ';'::text) AS string_agg
           FROM (public.dns_nameservers dn
             JOIN public.dns_nameserver_domain_subscription dnds ON ((dn.id = dnds.dns_nameserver_id)))
          WHERE (dnds.domain_subscription_id = ds.id)) AS waterfront_nameservers
   FROM (((((((((((((((((((((((public.subscriptions s
     JOIN public.products p ON ((s.product_uuid = p.uuid)))
     JOIN public.product_groups pg ON ((p.product_group_id = pg.id)))
     JOIN public.migrated_subscription_subscription mss ON ((s.id = mss.subscription_id)))
     JOIN public.migrated_subscriptions ms ON ((mss.migrated_subscription_id = ms.id)))
     JOIN public.migrated_customer_migrated_subscription mcms ON ((ms.id = mcms.migrated_subscription_id)))
     JOIN public.migrated_customers mc ON ((mcms.migrated_customer_id = mc.id)))
     JOIN public.customers c ON ((s.customer_id = c.id)))
     JOIN public.customer_addresses ca ON ((c.id = ca.customer_id)))
     LEFT JOIN public.customer_contacts cc ON ((c.id = cc.customer_id)))
     LEFT JOIN public.customer_wallets cw ON ((c.id = cw.customer_id)))
     LEFT JOIN public.domain_subscriptions ds ON ((s.uuid = ds.subscription_uuid)))
     LEFT JOIN public.providers dp ON ((ds.provider_id = dp.id)))
     LEFT JOIN public.domain_contacts dc ON (((c.id = dc.customer_id) AND (ds.contact_owner_id = dc.id))))
     LEFT JOIN public.domain_contact_provider dcdp ON ((dc.id = dcdp.domain_contact_id)))
     LEFT JOIN public.hosting_subscriptions hs ON ((s.uuid = hs.subscription_uuid)))
     LEFT JOIN public.providers hph ON ((hs.provider_id = hph.id)))
     LEFT JOIN public.providers hpm ON ((hs.mail_only_provider_id = hpm.id)))
     LEFT JOIN public.providers hps ON ((hs.sitebuilder_provider_id = hps.id)))
     LEFT JOIN public.hosting_servers hser ON ((hser.id = hs.server_id)))
     LEFT JOIN public.hosting_servers hsb ON ((hsb.id = hs.basekit_server_id)))
     LEFT JOIN public.hosting_servers hsm ON ((hsm.id = hs.mail_only_server_id)))
     LEFT JOIN public.ssl_subscriptions ss ON ((ss.subscription_uuid = s.uuid)))
     LEFT JOIN public.providers sp ON ((ss.provider_id = sp.id)))
  GROUP BY mc.reference_customer_number, c.id, c.customer_number, mc.enable_invoicing, mc.group_type, mc.reference_name, c.email, c.first_name, c.last_name, c.organization, c.coc_number, c.vat_number, c.vat_rate, ca.country_code, c.phone_country_code, c.phone_area_code, c.phone_subscriber_number, cw.id, cw.amount, cw.refund_requested_at, ms.reference_subscription_id, s.id, s.domain, p.slug, s.gross_price, s.net_price, pg.slug, s.administrative_status, s.technical_status, s.updated_at, s.end_date, s.cancel_date, s.next_billing_date, dp.slug, dcdp.external_contact, ds.id, hph.slug, hpm.slug, hps.slug, sp.slug, hser.hostname, hsm.hostname, hsb.hostname
  ORDER BY s.id DESC;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: mollie_customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mollie_customers (
    id bigint NOT NULL,
    mollie_customer_reference_id character varying(255) NOT NULL,
    customer_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: mollie_customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mollie_customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mollie_customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mollie_customers_id_seq OWNED BY public.mollie_customers.id;


--
-- Name: notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notes (
    id bigint NOT NULL,
    customer_id integer NOT NULL,
    subscription_id integer,
    note text NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    noted_by_uuid uuid,
    noted_by_metadata jsonb
);


--
-- Name: notes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notes_id_seq OWNED BY public.notes.id;


--
-- Name: nova_field_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nova_field_attachments (
    id integer NOT NULL,
    attachable_type character varying(255) NOT NULL,
    attachable_id bigint NOT NULL,
    attachment character varying(255) NOT NULL,
    disk character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nova_field_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nova_field_attachments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nova_field_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nova_field_attachments_id_seq OWNED BY public.nova_field_attachments.id;


--
-- Name: nova_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nova_notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data text NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: nova_pending_field_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nova_pending_field_attachments (
    id integer NOT NULL,
    draft_id character varying(255) NOT NULL,
    attachment character varying(255) NOT NULL,
    disk character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nova_pending_field_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nova_pending_field_attachments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nova_pending_field_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nova_pending_field_attachments_id_seq OWNED BY public.nova_pending_field_attachments.id;


--
-- Name: office365__kpn__product_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.office365__kpn__product_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: office365__kpn__product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.office365__kpn__product_id_seq OWNED BY public.microsoft365_kpn_product.id;


--
-- Name: office365__subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.office365__subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: office365__subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.office365__subscriptions_id_seq OWNED BY public.microsoft365_customer_info.id;


--
-- Name: office365__subscriptions_id_seq1; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.office365__subscriptions_id_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: office365__subscriptions_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.office365__subscriptions_id_seq1 OWNED BY public.microsoft365_subscriptions.id;


--
-- Name: one_off_scripts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.one_off_scripts (
    id bigint NOT NULL,
    slug character varying(255) NOT NULL,
    ticket_ref character varying(255),
    last_executed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    output_last_run text
);


--
-- Name: one_off_scripts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.one_off_scripts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: one_off_scripts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.one_off_scripts_id_seq OWNED BY public.one_off_scripts.id;


--
-- Name: one_time_service_invoice; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.one_time_service_invoice (
    one_time_service_id bigint NOT NULL,
    invoice_id integer NOT NULL
);


--
-- Name: one_time_services; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.one_time_services (
    id bigint NOT NULL,
    customer_id integer NOT NULL,
    subscription_id integer NOT NULL,
    product_id integer NOT NULL,
    amount integer NOT NULL,
    discount_percentage integer NOT NULL,
    gross_price integer NOT NULL,
    execution_date date NOT NULL,
    status character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    uuid uuid NOT NULL,
    CONSTRAINT one_time_services_status_check CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'in_progress'::character varying, 'done'::character varying])::text[])))
);


--
-- Name: one_time_services_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.one_time_services_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: one_time_services_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.one_time_services_id_seq OWNED BY public.one_time_services.id;


--
-- Name: order_line_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.order_line_items (
    id integer NOT NULL,
    order_id integer NOT NULL,
    product_uuid character(36),
    subscription_uuid character(36),
    domain character varying(255),
    gross_price integer,
    product_name character varying(255) NOT NULL,
    net_price integer,
    status character varying(255) NOT NULL,
    server integer,
    transfer_secret character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    billing_period integer NOT NULL,
    contract_period integer NOT NULL,
    parent_subscription_uuid character(36),
    parent_id bigint,
    meta_data text,
    one_time_service_id integer
);


--
-- Name: order_line_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.order_line_items_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: order_line_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.order_line_items_id_seq OWNED BY public.order_line_items.id;


--
-- Name: orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.orders (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    customer_id integer,
    total_price integer NOT NULL,
    environment character varying(255),
    redirect_url character varying(255),
    confirmation_url character varying(255),
    payment_method character varying(255) DEFAULT 'mollie'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(255) NOT NULL,
    ordered_by_uuid uuid,
    ordered_by_metadata jsonb,
    administration_fees integer DEFAULT 0 NOT NULL,
    is_invoiced boolean DEFAULT false NOT NULL
);


--
-- Name: orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.orders_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.orders_id_seq OWNED BY public.orders.id;


--
-- Name: parent_product; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.parent_product (
    parent_product_id integer NOT NULL,
    product_id integer NOT NULL
);


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id integer NOT NULL,
    external_id character varying(255) NOT NULL,
    customer_uuid character(36) NOT NULL,
    order_id integer,
    amount integer NOT NULL,
    status character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    create_direct_debit_mandate boolean
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: product_allowed_changes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_allowed_changes (
    id bigint NOT NULL,
    from_product_id integer NOT NULL,
    to_product_id integer NOT NULL,
    change_type character varying(255) NOT NULL,
    display_order integer NOT NULL,
    is_available_for_customer boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT product_allowed_changes_change_type_check CHECK (((change_type)::text = ANY ((ARRAY['upgrade'::character varying, 'downgrade'::character varying])::text[])))
);


--
-- Name: product_allowed_changes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_allowed_changes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_allowed_changes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_allowed_changes_id_seq OWNED BY public.product_allowed_changes.id;


--
-- Name: product_coupling; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_coupling (
    id integer NOT NULL,
    conditional_product_id integer NOT NULL,
    child_product_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    weight integer
);


--
-- Name: product_coupling_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_coupling_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_coupling_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_coupling_id_seq OWNED BY public.product_coupling.id;


--
-- Name: product_discounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_discounts (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    product_id integer
);


--
-- Name: product_discounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_discounts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_discounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_discounts_id_seq OWNED BY public.product_discounts.id;


--
-- Name: product_groups_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_groups_id_seq OWNED BY public.product_groups.id;


--
-- Name: product_introduction_discounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_introduction_discounts (
    id bigint NOT NULL,
    product_id integer NOT NULL,
    amount integer NOT NULL,
    contract_period integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: product_introduction_discounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_introduction_discounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_introduction_discounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_introduction_discounts_id_seq OWNED BY public.product_introduction_discounts.id;


--
-- Name: product_price_alternatives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_price_alternatives (
    id bigint NOT NULL,
    product_price_id bigint NOT NULL,
    alternative_product_id bigint NOT NULL,
    gross_price integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: product_price_alternatives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_price_alternatives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_price_alternatives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_price_alternatives_id_seq OWNED BY public.product_price_alternatives.id;


--
-- Name: product_prices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_prices_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_prices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_prices_id_seq OWNED BY public.product_prices.id;


--
-- Name: product_promotions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_promotions (
    id bigint NOT NULL,
    product_id bigint NOT NULL,
    platform character varying(255) NOT NULL,
    placement_url character varying(255) NOT NULL,
    start_date timestamp(0) without time zone NOT NULL,
    end_date timestamp(0) without time zone NOT NULL,
    call_to_action jsonb NOT NULL,
    weight integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT product_promotions_platform_check CHECK (((platform)::text = 'customer_panel'::text))
);


--
-- Name: product_promotions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_promotions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_promotions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_promotions_id_seq OWNED BY public.product_promotions.id;


--
-- Name: product_specs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.product_specs (
    id integer NOT NULL,
    product_id integer NOT NULL,
    name character varying(255) NOT NULL,
    value character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: product_specs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.product_specs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: product_specs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.product_specs_id_seq OWNED BY public.product_specs.id;


--
-- Name: products_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.products_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: products_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.products_id_seq OWNED BY public.products.id;


--
-- Name: provider_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.provider_settings (
    id bigint NOT NULL,
    key text NOT NULL,
    value text NOT NULL,
    provider_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: provider_settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.provider_settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: provider_settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.provider_settings_id_seq OWNED BY public.provider_settings.id;


--
-- Name: providers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.providers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: providers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.providers_id_seq OWNED BY public.providers.id;


--
-- Name: provisioning_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.provisioning_requests (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    tag character varying(255),
    request_data jsonb NOT NULL,
    request_type character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    request_name character varying(255) NOT NULL
);


--
-- Name: provisioning_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.provisioning_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: provisioning_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.provisioning_requests_id_seq OWNED BY public.provisioning_requests.id;


--
-- Name: provisioning_results; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.provisioning_results (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    request_id bigint NOT NULL,
    response jsonb NOT NULL,
    status character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: provisioning_results_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.provisioning_results_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: provisioning_results_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.provisioning_results_id_seq OWNED BY public.provisioning_results.id;


--
-- Name: reseller_hosting_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reseller_hosting_subscriptions (
    id integer NOT NULL,
    server_id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    provider_id integer NOT NULL,
    directadmin_customer_username character varying(255),
    plesk_customer_username character varying(255),
    plesk_customer_id integer,
    storage_type character varying(255) DEFAULT 'ssd'::character varying NOT NULL,
    disk_space integer,
    max_users integer,
    max_domains integer,
    max_email_addresses integer,
    max_traffic integer,
    max_databases integer,
    permissions json,
    last_created_result text,
    last_created_result_received timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: reseller_hosting__subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reseller_hosting__subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reseller_hosting__subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reseller_hosting__subscriptions_id_seq OWNED BY public.reseller_hosting_subscriptions.id;


--
-- Name: rtr_response_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.rtr_response_log (
    id bigint NOT NULL,
    source character varying(255) NOT NULL,
    response json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: rtr_response_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.rtr_response_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: rtr_response_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.rtr_response_log_id_seq OWNED BY public.rtr_response_log.id;


--
-- Name: spam_experts_clusters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.spam_experts_clusters (
    id bigint NOT NULL,
    hostname character varying(255) NOT NULL,
    business_unit character varying(255),
    username character varying(255) NOT NULL,
    password text NOT NULL,
    ssl boolean NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: spam_experts_clusters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.spam_experts_clusters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: spam_experts_clusters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.spam_experts_clusters_id_seq OWNED BY public.spam_experts_clusters.id;


--
-- Name: ssl_sanity; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ssl_sanity (
    id integer NOT NULL,
    subscription_uuid character(36) NOT NULL,
    product_name text,
    domain text,
    has_ssl_subscription boolean,
    has_ssl_certificate_files boolean,
    has_ssl_private_files boolean,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: ssl__sanity_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ssl__sanity_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ssl__sanity_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ssl__sanity_id_seq OWNED BY public.ssl_sanity.id;


--
-- Name: ssl__subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ssl__subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ssl__subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ssl__subscriptions_id_seq OWNED BY public.ssl_subscriptions.id;


--
-- Name: subscription_mutations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_mutations (
    id bigint NOT NULL,
    subscription_id integer NOT NULL,
    product_id integer NOT NULL,
    net_price integer NOT NULL,
    gross_price integer NOT NULL,
    billing_period integer NOT NULL,
    contract_period integer NOT NULL,
    mutated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscription_mutations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_mutations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_mutations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_mutations_id_seq OWNED BY public.subscription_mutations.id;


--
-- Name: subscription_transfer; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_transfer (
    transfer_id integer NOT NULL,
    subscription_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    executed_at timestamp(0) without time zone,
    failed_at timestamp(0) without time zone,
    reason_failed character varying(255)
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.templates (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    subject character varying(255) NOT NULL,
    header text,
    body text NOT NULL,
    footer text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    slug character varying(255) DEFAULT 'slug'::character varying NOT NULL,
    type character varying(255),
    hubspot_template_id character varying(255)
);


--
-- Name: templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.templates_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.templates_id_seq OWNED BY public.templates.id;


--
-- Name: transfers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transfers (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    from_customer_id integer NOT NULL,
    to_customer_id integer NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    canceled_at timestamp(0) without time zone,
    accepted_at timestamp(0) without time zone,
    rejected_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    started_at timestamp(0) without time zone
);


--
-- Name: transfers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transfers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transfers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transfers_id_seq OWNED BY public.transfers.id;


--
-- Name: translation_keys; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_keys (
    id bigint NOT NULL,
    key character varying(255) NOT NULL,
    source character varying(255) NOT NULL,
    context text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: translation_keys_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_keys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_keys_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_keys_id_seq OWNED BY public.translation_keys.id;


--
-- Name: translation_strings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.translation_strings (
    id bigint NOT NULL,
    language_id bigint NOT NULL,
    key_id bigint NOT NULL,
    translated_string text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: translation_strings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.translation_strings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: translation_strings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.translation_strings_id_seq OWNED BY public.translation_strings.id;


--
-- Name: voucher_claims; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.voucher_claims (
    id bigint NOT NULL,
    voucher_id bigint NOT NULL,
    order_line_item_id bigint NOT NULL,
    amount_claimed integer NOT NULL
);


--
-- Name: voucher_claims_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.voucher_claims_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: voucher_claims_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.voucher_claims_id_seq OWNED BY public.voucher_claims.id;


--
-- Name: vouchers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vouchers (
    id integer NOT NULL,
    uuid character(36) NOT NULL,
    display_name character varying(255) NOT NULL,
    product_uuid character(36),
    product_group_uuid character(36),
    code character varying(255) NOT NULL,
    amount integer NOT NULL,
    max_claims integer,
    expiration_date timestamp(0) without time zone,
    apply_with_discount boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    amount_type character varying(255) NOT NULL,
    internal_name character varying(255) NOT NULL,
    description character varying(255),
    allow_multiple_claims_same_customer boolean NOT NULL,
    billing_period integer,
    contract_period integer
);


--
-- Name: vouchers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vouchers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vouchers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vouchers_id_seq OWNED BY public.vouchers.id;


--
-- Name: action_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.action_events ALTER COLUMN id SET DEFAULT nextval('public.action_events_id_seq'::regclass);


--
-- Name: addon_providers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addon_providers ALTER COLUMN id SET DEFAULT nextval('public.addon_providers_id_seq'::regclass);


--
-- Name: addons id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addons ALTER COLUMN id SET DEFAULT nextval('public.addons_id_seq'::regclass);


--
-- Name: affiliate_product_group id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_product_group ALTER COLUMN id SET DEFAULT nextval('public.affiliate_product_group_id_seq'::regclass);


--
-- Name: affiliate_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions ALTER COLUMN id SET DEFAULT nextval('public.affiliate_transactions_id_seq'::regclass);


--
-- Name: affiliates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliates ALTER COLUMN id SET DEFAULT nextval('public.affiliates_id_seq'::regclass);


--
-- Name: audits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits ALTER COLUMN id SET DEFAULT nextval('public.audits_id_seq'::regclass);


--
-- Name: cloudstack_environment_products id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environment_products ALTER COLUMN id SET DEFAULT nextval('public.cloudstack__environment_products_id_seq'::regclass);


--
-- Name: cloudstack_environments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environments ALTER COLUMN id SET DEFAULT nextval('public.cloudstack__environments_id_seq'::regclass);


--
-- Name: cloudstack_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_jobs ALTER COLUMN id SET DEFAULT nextval('public.cloudstack_jobs_id_seq'::regclass);


--
-- Name: cloudstack_managerdomain_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.cloudstack__managerdomain_subscriptions_id_seq'::regclass);


--
-- Name: cloudstack_vm_ssh_keys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_ssh_keys ALTER COLUMN id SET DEFAULT nextval('public.cloudstack_vm_ssh_keys_id_seq'::regclass);


--
-- Name: cloudstack_vm_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.cloudstack__vm_subscriptions_id_seq'::regclass);


--
-- Name: cloudstack_volume_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_volume_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.cloudstack__volume_subscriptions_id_seq'::regclass);


--
-- Name: customer_addresses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_addresses ALTER COLUMN id SET DEFAULT nextval('public.customer_addresses_id_seq'::regclass);


--
-- Name: customer_contacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_contacts ALTER COLUMN id SET DEFAULT nextval('public.customer_contacts_id_seq'::regclass);


--
-- Name: customer_product_discount id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_discount ALTER COLUMN id SET DEFAULT nextval('public.customer_product_discount_id_seq'::regclass);


--
-- Name: customer_vat_errors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_vat_errors ALTER COLUMN id SET DEFAULT nextval('public.customer_vat_errors_id_seq'::regclass);


--
-- Name: customer_wallets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wallets ALTER COLUMN id SET DEFAULT nextval('public.customer_wallets_id_seq'::regclass);


--
-- Name: customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers ALTER COLUMN id SET DEFAULT nextval('public.customers_id_seq'::regclass);


--
-- Name: customers customer_number; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers ALTER COLUMN customer_number SET DEFAULT nextval('public.customer_number_increment'::regclass);


--
-- Name: dns_customer_template_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_template_records ALTER COLUMN id SET DEFAULT nextval('public.dns__customer_template_records_id_seq'::regclass);


--
-- Name: dns_customer_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_templates ALTER COLUMN id SET DEFAULT nextval('public.dns__customer_templates_id_seq'::regclass);


--
-- Name: dns_deployments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployments ALTER COLUMN id SET DEFAULT nextval('public.dns_deployments_id_seq'::regclass);


--
-- Name: dns_external_nameservers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_external_nameservers ALTER COLUMN id SET DEFAULT nextval('public.dns_external_nameservers_id_seq'::regclass);


--
-- Name: dns_nameservers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameservers ALTER COLUMN id SET DEFAULT nextval('public.dns__nameservers_id_seq'::regclass);


--
-- Name: dns_record_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_record_changes ALTER COLUMN id SET DEFAULT nextval('public.dns_record_changes_id_seq'::regclass);


--
-- Name: dns_regions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_regions ALTER COLUMN id SET DEFAULT nextval('public.dns__regions_id_seq'::regclass);


--
-- Name: dns_template_record_set_rows id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_set_rows ALTER COLUMN id SET DEFAULT nextval('public.dns__template_record_set_rows_id_seq'::regclass);


--
-- Name: dns_template_record_sets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_sets ALTER COLUMN id SET DEFAULT nextval('public.dns__template_record_sets_id_seq'::regclass);


--
-- Name: dns_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_templates ALTER COLUMN id SET DEFAULT nextval('public.dns__templates_id_seq'::regclass);


--
-- Name: dns_vanity_nameservers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_vanity_nameservers ALTER COLUMN id SET DEFAULT nextval('public.dns_vanity_nameservers_id_seq'::regclass);


--
-- Name: domain_contact_anonymous_handles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_anonymous_handles ALTER COLUMN id SET DEFAULT nextval('public.domain_contact_anonymous_handles_id_seq'::regclass);


--
-- Name: domain_contact_provider id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_provider ALTER COLUMN id SET DEFAULT nextval('public.domain_contact_domain_provider_id_seq'::regclass);


--
-- Name: domain_contacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contacts ALTER COLUMN id SET DEFAULT nextval('public.domain__contacts_id_seq'::regclass);


--
-- Name: domain_provider_status id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_provider_status ALTER COLUMN id SET DEFAULT nextval('public.domain_provider_status_id_seq'::regclass);


--
-- Name: domain_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.domain__subscriptions_id_seq'::regclass);


--
-- Name: email_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_history ALTER COLUMN id SET DEFAULT nextval('public.email_history_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: hosting_product_compositions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions ALTER COLUMN id SET DEFAULT nextval('public.hosting_product_compositions_id_seq'::regclass);


--
-- Name: hosting_redirecting_legacy_servers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_redirecting_legacy_servers ALTER COLUMN id SET DEFAULT nextval('public.hosting_forwarding_legacy_servers_id_seq'::regclass);


--
-- Name: hosting_servers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_servers ALTER COLUMN id SET DEFAULT nextval('public.hosting__servers_id_seq'::regclass);


--
-- Name: hosting_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.hosting__subscriptions_id_seq'::regclass);


--
-- Name: hubspot_events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hubspot_events ALTER COLUMN id SET DEFAULT nextval('public.hubspot_events_id_seq'::regclass);


--
-- Name: invoices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: labels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labels ALTER COLUMN id SET DEFAULT nextval('public.labels_id_seq'::regclass);


--
-- Name: mandates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandates ALTER COLUMN id SET DEFAULT nextval('public.mandates_id_seq'::regclass);


--
-- Name: microsoft365_customer_info id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_customer_info ALTER COLUMN id SET DEFAULT nextval('public.office365__subscriptions_id_seq'::regclass);


--
-- Name: microsoft365_http_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_http_log ALTER COLUMN id SET DEFAULT nextval('public.microsoft365_http_log_id_seq'::regclass);


--
-- Name: microsoft365_kpn_product id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_kpn_product ALTER COLUMN id SET DEFAULT nextval('public.office365__kpn__product_id_seq'::regclass);


--
-- Name: microsoft365_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.office365__subscriptions_id_seq1'::regclass);


--
-- Name: microsoft365_sync_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_sync_log ALTER COLUMN id SET DEFAULT nextval('public.microsoft365_sync_log_id_seq'::regclass);


--
-- Name: migrated_customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_customers ALTER COLUMN id SET DEFAULT nextval('public.migrated_customers_id_seq'::regclass);


--
-- Name: migrated_dns_template_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_template_records ALTER COLUMN id SET DEFAULT nextval('public.migrated_dns_template_records_id_seq'::regclass);


--
-- Name: migrated_dns_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_templates ALTER COLUMN id SET DEFAULT nextval('public.migrated_dns_templates_id_seq'::regclass);


--
-- Name: migrated_subscription_steps id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscription_steps ALTER COLUMN id SET DEFAULT nextval('public.migrated_subscription_steps_id_seq'::regclass);


--
-- Name: migrated_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.migrated_subscriptions_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: mollie_customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mollie_customers ALTER COLUMN id SET DEFAULT nextval('public.mollie_customers_id_seq'::regclass);


--
-- Name: notes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes ALTER COLUMN id SET DEFAULT nextval('public.notes_id_seq'::regclass);


--
-- Name: nova_field_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nova_field_attachments ALTER COLUMN id SET DEFAULT nextval('public.nova_field_attachments_id_seq'::regclass);


--
-- Name: nova_pending_field_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nova_pending_field_attachments ALTER COLUMN id SET DEFAULT nextval('public.nova_pending_field_attachments_id_seq'::regclass);


--
-- Name: one_off_scripts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_off_scripts ALTER COLUMN id SET DEFAULT nextval('public.one_off_scripts_id_seq'::regclass);


--
-- Name: one_time_services id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_services ALTER COLUMN id SET DEFAULT nextval('public.one_time_services_id_seq'::regclass);


--
-- Name: order_line_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_items ALTER COLUMN id SET DEFAULT nextval('public.order_line_items_id_seq'::regclass);


--
-- Name: orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders ALTER COLUMN id SET DEFAULT nextval('public.orders_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: product_allowed_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_allowed_changes ALTER COLUMN id SET DEFAULT nextval('public.product_allowed_changes_id_seq'::regclass);


--
-- Name: product_coupling id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_coupling ALTER COLUMN id SET DEFAULT nextval('public.product_coupling_id_seq'::regclass);


--
-- Name: product_discounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_discounts ALTER COLUMN id SET DEFAULT nextval('public.product_discounts_id_seq'::regclass);


--
-- Name: product_groups id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups ALTER COLUMN id SET DEFAULT nextval('public.product_groups_id_seq'::regclass);


--
-- Name: product_introduction_discounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_introduction_discounts ALTER COLUMN id SET DEFAULT nextval('public.product_introduction_discounts_id_seq'::regclass);


--
-- Name: product_price_alternatives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price_alternatives ALTER COLUMN id SET DEFAULT nextval('public.product_price_alternatives_id_seq'::regclass);


--
-- Name: product_prices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices ALTER COLUMN id SET DEFAULT nextval('public.product_prices_id_seq'::regclass);


--
-- Name: product_promotions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_promotions ALTER COLUMN id SET DEFAULT nextval('public.product_promotions_id_seq'::regclass);


--
-- Name: product_specs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_specs ALTER COLUMN id SET DEFAULT nextval('public.product_specs_id_seq'::regclass);


--
-- Name: products id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products ALTER COLUMN id SET DEFAULT nextval('public.products_id_seq'::regclass);


--
-- Name: provider_settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_settings ALTER COLUMN id SET DEFAULT nextval('public.provider_settings_id_seq'::regclass);


--
-- Name: providers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.providers ALTER COLUMN id SET DEFAULT nextval('public.providers_id_seq'::regclass);


--
-- Name: provisioning_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_requests ALTER COLUMN id SET DEFAULT nextval('public.provisioning_requests_id_seq'::regclass);


--
-- Name: provisioning_results id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_results ALTER COLUMN id SET DEFAULT nextval('public.provisioning_results_id_seq'::regclass);


--
-- Name: reseller_hosting_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.reseller_hosting__subscriptions_id_seq'::regclass);


--
-- Name: rtr_response_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rtr_response_log ALTER COLUMN id SET DEFAULT nextval('public.rtr_response_log_id_seq'::regclass);


--
-- Name: spam_experts_clusters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_experts_clusters ALTER COLUMN id SET DEFAULT nextval('public.spam_experts_clusters_id_seq'::regclass);


--
-- Name: ssl_sanity id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_sanity ALTER COLUMN id SET DEFAULT nextval('public.ssl__sanity_id_seq'::regclass);


--
-- Name: ssl_subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_subscriptions ALTER COLUMN id SET DEFAULT nextval('public.ssl__subscriptions_id_seq'::regclass);


--
-- Name: subscription_changes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_changes ALTER COLUMN id SET DEFAULT nextval('public.hosting__upgrades_id_seq'::regclass);


--
-- Name: subscription_mutations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_mutations ALTER COLUMN id SET DEFAULT nextval('public.subscription_mutations_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates ALTER COLUMN id SET DEFAULT nextval('public.templates_id_seq'::regclass);


--
-- Name: transfers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfers ALTER COLUMN id SET DEFAULT nextval('public.transfers_id_seq'::regclass);


--
-- Name: translation_keys id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys ALTER COLUMN id SET DEFAULT nextval('public.translation_keys_id_seq'::regclass);


--
-- Name: translation_languages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_languages ALTER COLUMN id SET DEFAULT nextval('public.languages_id_seq'::regclass);


--
-- Name: translation_strings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_strings ALTER COLUMN id SET DEFAULT nextval('public.translation_strings_id_seq'::regclass);


--
-- Name: voucher_claims id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voucher_claims ALTER COLUMN id SET DEFAULT nextval('public.voucher_claims_id_seq'::regclass);


--
-- Name: vouchers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vouchers ALTER COLUMN id SET DEFAULT nextval('public.vouchers_id_seq'::regclass);


--
-- Name: action_events action_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.action_events
    ADD CONSTRAINT action_events_pkey PRIMARY KEY (id);


--
-- Name: addon_providers addon_providers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addon_providers
    ADD CONSTRAINT addon_providers_pkey PRIMARY KEY (id);


--
-- Name: addon_providers addon_providers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addon_providers
    ADD CONSTRAINT addon_providers_uuid_unique UNIQUE (uuid);


--
-- Name: addons addons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.addons
    ADD CONSTRAINT addons_pkey PRIMARY KEY (id);


--
-- Name: affiliate_customer affiliate_customer_customer_id_affiliate_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_customer
    ADD CONSTRAINT affiliate_customer_customer_id_affiliate_id_unique UNIQUE (customer_id, affiliate_id);


--
-- Name: affiliate_product_group affiliate_product_group_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_product_group
    ADD CONSTRAINT affiliate_product_group_pkey PRIMARY KEY (id);


--
-- Name: affiliate_transactions affiliate_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_pkey PRIMARY KEY (id);


--
-- Name: affiliates affiliates_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliates
    ADD CONSTRAINT affiliates_key_unique UNIQUE (key);


--
-- Name: affiliates affiliates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliates
    ADD CONSTRAINT affiliates_pkey PRIMARY KEY (id);


--
-- Name: audits audits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audits
    ADD CONSTRAINT audits_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_environment_products cloudstack__environment_products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environment_products
    ADD CONSTRAINT cloudstack__environment_products_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_environments cloudstack__environments_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environments
    ADD CONSTRAINT cloudstack__environments_name_unique UNIQUE (name);


--
-- Name: cloudstack_environments cloudstack__environments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environments
    ADD CONSTRAINT cloudstack__environments_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_environments cloudstack__environments_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environments
    ADD CONSTRAINT cloudstack__environments_slug_unique UNIQUE (slug);


--
-- Name: cloudstack_managerdomain_subscriptions cloudstack__managerdomain_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_subscriptions
    ADD CONSTRAINT cloudstack__managerdomain_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_managerdomain_subscriptions cloudstack__managerdomain_subscriptions_subscription_uuid_uniqu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_subscriptions
    ADD CONSTRAINT cloudstack__managerdomain_subscriptions_subscription_uuid_uniqu UNIQUE (subscription_uuid);


--
-- Name: cloudstack_vm_subscriptions cloudstack__vm_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_subscriptions
    ADD CONSTRAINT cloudstack__vm_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_vm_subscriptions cloudstack__vm_subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_subscriptions
    ADD CONSTRAINT cloudstack__vm_subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: cloudstack_volume_subscriptions cloudstack__volume_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_volume_subscriptions
    ADD CONSTRAINT cloudstack__volume_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_volume_subscriptions cloudstack__volume_subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_volume_subscriptions
    ADD CONSTRAINT cloudstack__volume_subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: cloudstack_jobs cloudstack_jobs_job_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_jobs
    ADD CONSTRAINT cloudstack_jobs_job_id_unique UNIQUE (job_id);


--
-- Name: cloudstack_jobs cloudstack_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_jobs
    ADD CONSTRAINT cloudstack_jobs_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_managerdomain_cloudstack_vm_ssh_keys cloudstack_managerdomain_cloudstack_vm_ssh_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_managerdomain_cloudstack_vm_ssh_keys_pkey PRIMARY KEY (manager_domain_deployment_id, ssh_key_id);


--
-- Name: cloudstack_vm_deployment_ssh_key cloudstack_vm_deployment_ssh_key_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_deployment_ssh_key
    ADD CONSTRAINT cloudstack_vm_deployment_ssh_key_pkey PRIMARY KEY (vm_deployment_id, ssh_key_id);


--
-- Name: cloudstack_vm_ssh_keys cloudstack_vm_ssh_keys_customer_id_fingerprint_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_vm_ssh_keys_customer_id_fingerprint_unique UNIQUE (customer_id, fingerprint);


--
-- Name: cloudstack_vm_ssh_keys cloudstack_vm_ssh_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_vm_ssh_keys_pkey PRIMARY KEY (id);


--
-- Name: cloudstack_vm_ssh_keys cloudstack_vm_ssh_keys_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_vm_ssh_keys_uuid_unique UNIQUE (uuid);


--
-- Name: customer_addresses customer_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_addresses
    ADD CONSTRAINT customer_addresses_pkey PRIMARY KEY (id);


--
-- Name: customer_contacts customer_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_contacts
    ADD CONSTRAINT customer_contacts_pkey PRIMARY KEY (id);


--
-- Name: customer_contacts customer_contacts_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_contacts
    ADD CONSTRAINT customer_contacts_uuid_unique UNIQUE (uuid);


--
-- Name: customer_customers customer_customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_customers
    ADD CONSTRAINT customer_customers_pkey PRIMARY KEY (parent_customer_id, child_customer_id);


--
-- Name: customer_product_discount customer_product_discount_customer_id_product_discount_id_uniqu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_discount
    ADD CONSTRAINT customer_product_discount_customer_id_product_discount_id_uniqu UNIQUE (customer_id, product_discount_id);


--
-- Name: customer_product_discount customer_product_discount_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_discount
    ADD CONSTRAINT customer_product_discount_pkey PRIMARY KEY (id);


--
-- Name: customer_product_group customer_product_group_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_group
    ADD CONSTRAINT customer_product_group_pkey PRIMARY KEY (customer_id, product_group_id);


--
-- Name: customer_vat_errors customer_vat_errors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_vat_errors
    ADD CONSTRAINT customer_vat_errors_pkey PRIMARY KEY (id);


--
-- Name: customer_wallets customer_wallets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wallets
    ADD CONSTRAINT customer_wallets_pkey PRIMARY KEY (id);


--
-- Name: customers customers_customer_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_customer_number_unique UNIQUE (customer_number);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: customers customers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_uuid_unique UNIQUE (uuid);


--
-- Name: reseller_hosting_subscriptions da_username; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT da_username UNIQUE (directadmin_customer_username);


--
-- Name: dns_customer_template_records dns__customer_template_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_template_records
    ADD CONSTRAINT dns__customer_template_records_pkey PRIMARY KEY (id);


--
-- Name: dns_customer_templates dns__customer_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_templates
    ADD CONSTRAINT dns__customer_templates_pkey PRIMARY KEY (id);


--
-- Name: dns_nameserver_domain_subscription dns__nameserver_domain__subscription_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameserver_domain_subscription
    ADD CONSTRAINT dns__nameserver_domain__subscription_pkey PRIMARY KEY (dns_nameserver_id, domain_subscription_id);


--
-- Name: dns_nameservers dns__nameservers_nameserver_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameservers
    ADD CONSTRAINT dns__nameservers_nameserver_unique UNIQUE (nameserver);


--
-- Name: dns_nameservers dns__nameservers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameservers
    ADD CONSTRAINT dns__nameservers_pkey PRIMARY KEY (id);


--
-- Name: dns_regions dns__regions_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_regions
    ADD CONSTRAINT dns__regions_name_unique UNIQUE (name);


--
-- Name: dns_regions dns__regions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_regions
    ADD CONSTRAINT dns__regions_pkey PRIMARY KEY (id);


--
-- Name: dns_template_record_set_rows dns__template_record_set_rows_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_set_rows
    ADD CONSTRAINT dns__template_record_set_rows_pkey PRIMARY KEY (id);


--
-- Name: dns_template_record_sets dns__template_record_sets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_sets
    ADD CONSTRAINT dns__template_record_sets_pkey PRIMARY KEY (id);


--
-- Name: dns_templates dns__templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_templates
    ADD CONSTRAINT dns__templates_pkey PRIMARY KEY (id);


--
-- Name: dns_templates dns__templates_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_templates
    ADD CONSTRAINT dns__templates_slug_unique UNIQUE (slug);


--
-- Name: dns_deployment_dns_vanity_nameserver dns_deployment_dns_vanity_nameserver_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployment_dns_vanity_nameserver
    ADD CONSTRAINT dns_deployment_dns_vanity_nameserver_pkey PRIMARY KEY (dns_deployment_id, dns_vanity_nameserver_id);


--
-- Name: dns_deployments dns_deployments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployments
    ADD CONSTRAINT dns_deployments_pkey PRIMARY KEY (id);


--
-- Name: dns_deployments dns_deployments_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployments
    ADD CONSTRAINT dns_deployments_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: dns_external_nameservers dns_external_nameservers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_external_nameservers
    ADD CONSTRAINT dns_external_nameservers_pkey PRIMARY KEY (id);


--
-- Name: dns_record_changes dns_record_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_record_changes
    ADD CONSTRAINT dns_record_changes_pkey PRIMARY KEY (id);


--
-- Name: dns_vanity_nameservers dns_vanity_nameservers_nameserver_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_vanity_nameservers
    ADD CONSTRAINT dns_vanity_nameservers_nameserver_unique UNIQUE (nameserver);


--
-- Name: dns_vanity_nameservers dns_vanity_nameservers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_vanity_nameservers
    ADD CONSTRAINT dns_vanity_nameservers_pkey PRIMARY KEY (id);


--
-- Name: domain_contacts domain__contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contacts
    ADD CONSTRAINT domain__contacts_pkey PRIMARY KEY (id);


--
-- Name: domain_contacts domain__contacts_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contacts
    ADD CONSTRAINT domain__contacts_uuid_unique UNIQUE (uuid);


--
-- Name: domain_subscriptions domain__subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions
    ADD CONSTRAINT domain__subscriptions_pkey PRIMARY KEY (id);


--
-- Name: domain_subscriptions domain__subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions
    ADD CONSTRAINT domain__subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: domain_contact_anonymous_handles domain_contact_anonymous_handles_handle_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_anonymous_handles
    ADD CONSTRAINT domain_contact_anonymous_handles_handle_unique UNIQUE (handle);


--
-- Name: domain_contact_anonymous_handles domain_contact_anonymous_handles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_anonymous_handles
    ADD CONSTRAINT domain_contact_anonymous_handles_pkey PRIMARY KEY (id);


--
-- Name: domain_contact_provider domain_contact_domain_provider_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_provider
    ADD CONSTRAINT domain_contact_domain_provider_pkey PRIMARY KEY (id);


--
-- Name: domain_provider_status domain_provider_status_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_provider_status
    ADD CONSTRAINT domain_provider_status_pkey PRIMARY KEY (id);


--
-- Name: email_history email_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_history
    ADD CONSTRAINT email_history_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: hosting_servers hosting__servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_servers
    ADD CONSTRAINT hosting__servers_pkey PRIMARY KEY (id);


--
-- Name: hosting_subscriptions hosting__subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting__subscriptions_pkey PRIMARY KEY (id);


--
-- Name: hosting_subscriptions hosting__subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting__subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: subscription_changes hosting__upgrades_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_changes
    ADD CONSTRAINT hosting__upgrades_pkey PRIMARY KEY (id);


--
-- Name: subscription_changes hosting__upgrades_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_changes
    ADD CONSTRAINT hosting__upgrades_uuid_unique UNIQUE (uuid);


--
-- Name: hosting_redirecting_legacy_servers hosting_forwarding_legacy_servers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_redirecting_legacy_servers
    ADD CONSTRAINT hosting_forwarding_legacy_servers_pkey PRIMARY KEY (id);


--
-- Name: hosting_product_compositions hosting_product_compositions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_pkey PRIMARY KEY (id);


--
-- Name: hosting_redirecting_legacy_servers hosting_redirecting_legacy_servers_hostname_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_redirecting_legacy_servers
    ADD CONSTRAINT hosting_redirecting_legacy_servers_hostname_unique UNIQUE (hostname);


--
-- Name: hosting_redirecting_legacy_servers hosting_redirecting_legacy_servers_ipv4_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_redirecting_legacy_servers
    ADD CONSTRAINT hosting_redirecting_legacy_servers_ipv4_unique UNIQUE (ipv4);


--
-- Name: hosting_redirecting_legacy_servers hosting_redirecting_legacy_servers_ipv6_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_redirecting_legacy_servers
    ADD CONSTRAINT hosting_redirecting_legacy_servers_ipv6_unique UNIQUE (ipv6);


--
-- Name: hubspot_events hubspot_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hubspot_events
    ADD CONSTRAINT hubspot_events_pkey PRIMARY KEY (id);


--
-- Name: one_time_service_invoice invoice_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_service_invoice
    ADD CONSTRAINT invoice_id_unique UNIQUE (invoice_id);


--
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: label_subscription label_subscription_label_id_subscription_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_subscription
    ADD CONSTRAINT label_subscription_label_id_subscription_id_unique UNIQUE (label_id, subscription_id);


--
-- Name: labels labels_customer_id_value_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labels
    ADD CONSTRAINT labels_customer_id_value_unique UNIQUE (customer_id, value);


--
-- Name: labels labels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labels
    ADD CONSTRAINT labels_pkey PRIMARY KEY (id);


--
-- Name: translation_languages languages_locale_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_languages
    ADD CONSTRAINT languages_locale_unique UNIQUE (locale);


--
-- Name: translation_languages languages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_languages
    ADD CONSTRAINT languages_pkey PRIMARY KEY (id);


--
-- Name: mandate_migrated_customer mandate_migrated_customer_mandate_id_migrated_customer_id_uniqu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandate_migrated_customer
    ADD CONSTRAINT mandate_migrated_customer_mandate_id_migrated_customer_id_uniqu UNIQUE (mandate_id, migrated_customer_id);


--
-- Name: mandates mandates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandates
    ADD CONSTRAINT mandates_pkey PRIMARY KEY (id);


--
-- Name: microsoft365_http_log microsoft365_http_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_http_log
    ADD CONSTRAINT microsoft365_http_log_pkey PRIMARY KEY (id);


--
-- Name: microsoft365_subscriptions microsoft365_subscriptions_kpn_order_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions
    ADD CONSTRAINT microsoft365_subscriptions_kpn_order_id_unique UNIQUE (kpn_order_id);


--
-- Name: microsoft365_sync_log microsoft365_sync_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_sync_log
    ADD CONSTRAINT microsoft365_sync_log_pkey PRIMARY KEY (id);


--
-- Name: migrated_customers migrated_customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_customers
    ADD CONSTRAINT migrated_customers_pkey PRIMARY KEY (id);


--
-- Name: migrated_dns_template_records migrated_dns_template_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_template_records
    ADD CONSTRAINT migrated_dns_template_records_pkey PRIMARY KEY (id);


--
-- Name: migrated_dns_templates migrated_dns_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_templates
    ADD CONSTRAINT migrated_dns_templates_pkey PRIMARY KEY (id);


--
-- Name: migrated_subscription_steps migrated_subscription_steps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscription_steps
    ADD CONSTRAINT migrated_subscription_steps_pkey PRIMARY KEY (id);


--
-- Name: migrated_subscriptions migrated_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscriptions
    ADD CONSTRAINT migrated_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: mollie_customers mollie_customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mollie_customers
    ADD CONSTRAINT mollie_customers_pkey PRIMARY KEY (id);


--
-- Name: notes notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_pkey PRIMARY KEY (id);


--
-- Name: nova_field_attachments nova_field_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nova_field_attachments
    ADD CONSTRAINT nova_field_attachments_pkey PRIMARY KEY (id);


--
-- Name: nova_notifications nova_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nova_notifications
    ADD CONSTRAINT nova_notifications_pkey PRIMARY KEY (id);


--
-- Name: nova_pending_field_attachments nova_pending_field_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nova_pending_field_attachments
    ADD CONSTRAINT nova_pending_field_attachments_pkey PRIMARY KEY (id);


--
-- Name: microsoft365_kpn_product office365__kpn__product_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_kpn_product
    ADD CONSTRAINT office365__kpn__product_pkey PRIMARY KEY (id);


--
-- Name: microsoft365_customer_info office365__subscriptions_kpn_customer_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_customer_info
    ADD CONSTRAINT office365__subscriptions_kpn_customer_id_unique UNIQUE (kpn_customer_id);


--
-- Name: microsoft365_subscriptions office365__subscriptions_parent_subscription_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions
    ADD CONSTRAINT office365__subscriptions_parent_subscription_id_unique UNIQUE (subscription_id);


--
-- Name: microsoft365_customer_info office365__subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_customer_info
    ADD CONSTRAINT office365__subscriptions_pkey PRIMARY KEY (id);


--
-- Name: microsoft365_subscriptions office365__subscriptions_pkey1; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions
    ADD CONSTRAINT office365__subscriptions_pkey1 PRIMARY KEY (id);


--
-- Name: microsoft365_customer_info office365_customer_info_tenant_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_customer_info
    ADD CONSTRAINT office365_customer_info_tenant_name_unique UNIQUE (tenant_name);


--
-- Name: one_off_scripts one_off_scripts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_off_scripts
    ADD CONSTRAINT one_off_scripts_pkey PRIMARY KEY (id);


--
-- Name: one_off_scripts one_off_scripts_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_off_scripts
    ADD CONSTRAINT one_off_scripts_slug_unique UNIQUE (slug);


--
-- Name: one_time_services one_time_services_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_services
    ADD CONSTRAINT one_time_services_pkey PRIMARY KEY (id);


--
-- Name: order_line_items order_line_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_items
    ADD CONSTRAINT order_line_items_pkey PRIMARY KEY (id);


--
-- Name: orders orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_pkey PRIMARY KEY (id);


--
-- Name: orders orders_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_uuid_unique UNIQUE (uuid);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: reseller_hosting_subscriptions plesk_username; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT plesk_username UNIQUE (plesk_customer_username);


--
-- Name: product_allowed_changes product_allowed_changes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_allowed_changes
    ADD CONSTRAINT product_allowed_changes_pkey PRIMARY KEY (id);


--
-- Name: product_coupling product_coupling_conditional_product_id_child_product_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_coupling
    ADD CONSTRAINT product_coupling_conditional_product_id_child_product_id_unique UNIQUE (conditional_product_id, child_product_id);


--
-- Name: product_coupling product_coupling_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_coupling
    ADD CONSTRAINT product_coupling_pkey PRIMARY KEY (id);


--
-- Name: product_discounts product_discounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_discounts
    ADD CONSTRAINT product_discounts_pkey PRIMARY KEY (id);


--
-- Name: product_groups product_groups_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_pkey PRIMARY KEY (id);


--
-- Name: product_groups product_groups_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_slug_unique UNIQUE (slug);


--
-- Name: product_groups product_groups_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_groups
    ADD CONSTRAINT product_groups_uuid_unique UNIQUE (uuid);


--
-- Name: product_introduction_discounts product_introduction_discounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_introduction_discounts
    ADD CONSTRAINT product_introduction_discounts_pkey PRIMARY KEY (id);


--
-- Name: product_price_alternatives product_price_alternatives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price_alternatives
    ADD CONSTRAINT product_price_alternatives_pkey PRIMARY KEY (id);


--
-- Name: product_prices product_prices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_pkey PRIMARY KEY (id);


--
-- Name: product_promotions product_promotions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_promotions
    ADD CONSTRAINT product_promotions_pkey PRIMARY KEY (id);


--
-- Name: product_specs product_specs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_specs
    ADD CONSTRAINT product_specs_pkey PRIMARY KEY (id);


--
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (id);


--
-- Name: products products_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_slug_unique UNIQUE (slug);


--
-- Name: products products_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_uuid_unique UNIQUE (uuid);


--
-- Name: provider_settings provider_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_settings
    ADD CONSTRAINT provider_settings_pkey PRIMARY KEY (id);


--
-- Name: provider_settings provider_settings_provider_id_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_settings
    ADD CONSTRAINT provider_settings_provider_id_key_unique UNIQUE (provider_id, key);


--
-- Name: providers providers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.providers
    ADD CONSTRAINT providers_pkey PRIMARY KEY (id);


--
-- Name: providers providers_type_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.providers
    ADD CONSTRAINT providers_type_slug_unique UNIQUE (type, slug);


--
-- Name: provisioning_requests provisioning_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_requests
    ADD CONSTRAINT provisioning_requests_pkey PRIMARY KEY (id);


--
-- Name: provisioning_results provisioning_results_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_results
    ADD CONSTRAINT provisioning_results_pkey PRIMARY KEY (id);


--
-- Name: provisioning_results provisioning_results_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_results
    ADD CONSTRAINT provisioning_results_uuid_unique UNIQUE (uuid);


--
-- Name: reseller_hosting_subscriptions reseller_hosting__subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT reseller_hosting__subscriptions_pkey PRIMARY KEY (id);


--
-- Name: reseller_hosting_subscriptions reseller_hosting__subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT reseller_hosting__subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: rtr_response_log rtr_response_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.rtr_response_log
    ADD CONSTRAINT rtr_response_log_pkey PRIMARY KEY (id);


--
-- Name: spam_experts_clusters spam_experts_clusters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.spam_experts_clusters
    ADD CONSTRAINT spam_experts_clusters_pkey PRIMARY KEY (id);


--
-- Name: ssl_sanity ssl__sanity_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_sanity
    ADD CONSTRAINT ssl__sanity_pkey PRIMARY KEY (id);


--
-- Name: ssl_sanity ssl__sanity_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_sanity
    ADD CONSTRAINT ssl__sanity_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: ssl_subscriptions ssl__subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_subscriptions
    ADD CONSTRAINT ssl__subscriptions_pkey PRIMARY KEY (id);


--
-- Name: ssl_subscriptions ssl__subscriptions_subscription_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_subscriptions
    ADD CONSTRAINT ssl__subscriptions_subscription_uuid_unique UNIQUE (subscription_uuid);


--
-- Name: subscription_mutations subscription_mutations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_mutations
    ADD CONSTRAINT subscription_mutations_pkey PRIMARY KEY (id);


--
-- Name: subscription_transfer subscription_transfer_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_transfer
    ADD CONSTRAINT subscription_transfer_pkey PRIMARY KEY (transfer_id, subscription_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_uuid_unique UNIQUE (uuid);


--
-- Name: templates templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.templates
    ADD CONSTRAINT templates_pkey PRIMARY KEY (id);


--
-- Name: transfers transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_pkey PRIMARY KEY (id);


--
-- Name: transfers transfers_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_uuid_unique UNIQUE (uuid);


--
-- Name: translation_keys translation_keys_key_source_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys
    ADD CONSTRAINT translation_keys_key_source_unique UNIQUE (key, source);


--
-- Name: translation_keys translation_keys_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_keys
    ADD CONSTRAINT translation_keys_pkey PRIMARY KEY (id);


--
-- Name: translation_strings translation_strings_language_id_key_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_strings
    ADD CONSTRAINT translation_strings_language_id_key_id_unique UNIQUE (language_id, key_id);


--
-- Name: translation_strings translation_strings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_strings
    ADD CONSTRAINT translation_strings_pkey PRIMARY KEY (id);


--
-- Name: customer_contacts unique_contacts; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_contacts
    ADD CONSTRAINT unique_contacts UNIQUE (customer_id, email, type);


--
-- Name: product_prices unique_discounts; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT unique_discounts UNIQUE (product_id, product_discount_id, billing_period, type);


--
-- Name: product_prices unique_product_price; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT unique_product_price UNIQUE NULLS NOT DISTINCT (product_id, billing_period, contract_period, type, product_discount_id);


--
-- Name: voucher_claims voucher_claims_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voucher_claims
    ADD CONSTRAINT voucher_claims_pkey PRIMARY KEY (id);


--
-- Name: vouchers vouchers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vouchers
    ADD CONSTRAINT vouchers_pkey PRIMARY KEY (id);


--
-- Name: action_events_actionable_type_actionable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX action_events_actionable_type_actionable_id_index ON public.action_events USING btree (actionable_type, actionable_id);


--
-- Name: action_events_batch_id_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX action_events_batch_id_model_type_model_id_index ON public.action_events USING btree (batch_id, model_type, model_id);


--
-- Name: action_events_target_type_target_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX action_events_target_type_target_id_index ON public.action_events USING btree (target_type, target_id);


--
-- Name: addons_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX addons_name_index ON public.addons USING btree (name);


--
-- Name: audits_auditable_type_auditable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audits_auditable_type_auditable_id_index ON public.audits USING btree (auditable_type, auditable_id);


--
-- Name: audits_user_id_user_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX audits_user_id_user_type_index ON public.audits USING btree (user_id, user_type);


--
-- Name: cloudstack_vm_ssh_keys_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cloudstack_vm_ssh_keys_customer_id_index ON public.cloudstack_vm_ssh_keys USING btree (customer_id);


--
-- Name: customer_customers_child_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_customers_child_customer_id_index ON public.customer_customers USING btree (child_customer_id);


--
-- Name: customer_customers_parent_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_customers_parent_customer_id_index ON public.customer_customers USING btree (parent_customer_id);


--
-- Name: customer_product_group_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_product_group_customer_id_index ON public.customer_product_group USING btree (customer_id);


--
-- Name: customer_product_group_product_group_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customer_product_group_product_group_id_index ON public.customer_product_group USING btree (product_group_id);


--
-- Name: dns__customer_templates_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX dns__customer_templates_customer_id_index ON public.dns_customer_templates USING btree (customer_id);


--
-- Name: hosting__subscriptions_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hosting__subscriptions_server_id_index ON public.hosting_subscriptions USING btree (server_id);


--
-- Name: idx_customer_subscription_latest_updated; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_customer_subscription_latest_updated ON public.hubspot_events USING btree (customer_id, hubspot_object_uuid, latest, updated_at);


--
-- Name: idx_order_line_items_subscription_uuid; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_order_line_items_subscription_uuid ON public.order_line_items USING btree (subscription_uuid);


--
-- Name: invoices_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invoices_customer_id_index ON public.invoices USING btree (customer_id);


--
-- Name: invoices_parent_invoice_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invoices_parent_invoice_id_index ON public.invoices USING btree (parent_invoice_id);


--
-- Name: invoices_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX invoices_subscription_id_index ON public.invoices USING btree (subscription_id);


--
-- Name: jobs_queue_reserved_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_reserved_at_index ON public.jobs USING btree (queue, reserved_at);


--
-- Name: languages_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX languages_active_index ON public.translation_languages USING btree (active);


--
-- Name: migrated_subscription_steps_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX migrated_subscription_steps_subscription_id_index ON public.migrated_subscription_steps USING btree (subscription_id);


--
-- Name: nova_field_attachments_attachable_type_attachable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nova_field_attachments_attachable_type_attachable_id_index ON public.nova_field_attachments USING btree (attachable_type, attachable_id);


--
-- Name: nova_field_attachments_url_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nova_field_attachments_url_index ON public.nova_field_attachments USING btree (url);


--
-- Name: nova_notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nova_notifications_notifiable_type_notifiable_id_index ON public.nova_notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: nova_pending_field_attachments_draft_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nova_pending_field_attachments_draft_id_index ON public.nova_pending_field_attachments USING btree (draft_id);


--
-- Name: one_time_service_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX one_time_service_status_index ON public.one_time_services USING btree (status);


--
-- Name: order_line_items_order_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX order_line_items_order_id_index ON public.order_line_items USING btree (order_id);


--
-- Name: payments_customer_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_customer_uuid_index ON public.payments USING btree (customer_uuid);


--
-- Name: product_coupling_child_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX product_coupling_child_product_id_index ON public.product_coupling USING btree (child_product_id);


--
-- Name: product_coupling_conditional_product_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX product_coupling_conditional_product_id_index ON public.product_coupling USING btree (conditional_product_id);


--
-- Name: reseller_hosting__subscriptions_server_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reseller_hosting__subscriptions_server_id_index ON public.reseller_hosting_subscriptions USING btree (server_id);


--
-- Name: subscriptions_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_customer_id_index ON public.subscriptions USING btree (customer_id);


--
-- Name: subscriptions_domain_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_domain_index ON public.subscriptions USING btree (domain);


--
-- Name: subscriptions_parent_subscription_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_parent_subscription_id_index ON public.subscriptions USING btree (parent_subscription_id);


--
-- Name: subscriptions_product_uuid_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_product_uuid_index ON public.subscriptions USING btree (product_uuid);


--
-- Name: subscriptions_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_status_index ON public.subscriptions USING btree (administrative_status, technical_status);


--
-- Name: templates_title_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX templates_title_index ON public.templates USING btree (title);


--
-- Name: affiliate_customer affiliate_customer_affiliate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_customer
    ADD CONSTRAINT affiliate_customer_affiliate_id_foreign FOREIGN KEY (affiliate_id) REFERENCES public.affiliates(id);


--
-- Name: affiliate_customer affiliate_customer_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_customer
    ADD CONSTRAINT affiliate_customer_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: affiliate_product_group affiliate_product_group_affiliate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_product_group
    ADD CONSTRAINT affiliate_product_group_affiliate_id_foreign FOREIGN KEY (affiliate_id) REFERENCES public.affiliates(id);


--
-- Name: affiliate_product_group affiliate_product_group_product_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_product_group
    ADD CONSTRAINT affiliate_product_group_product_group_id_foreign FOREIGN KEY (product_group_id) REFERENCES public.product_groups(id);


--
-- Name: affiliate_transactions affiliate_transactions_affiliate_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_affiliate_customer_id_foreign FOREIGN KEY (affiliate_customer_id) REFERENCES public.customers(id);


--
-- Name: affiliate_transactions affiliate_transactions_affiliate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_affiliate_id_foreign FOREIGN KEY (affiliate_id) REFERENCES public.affiliates(id);


--
-- Name: affiliate_transactions affiliate_transactions_affiliate_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_affiliate_invoice_id_foreign FOREIGN KEY (affiliate_invoice_id) REFERENCES public.invoices(id);


--
-- Name: affiliate_transactions affiliate_transactions_original_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_original_customer_id_foreign FOREIGN KEY (original_customer_id) REFERENCES public.customers(id);


--
-- Name: affiliate_transactions affiliate_transactions_original_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliate_transactions
    ADD CONSTRAINT affiliate_transactions_original_invoice_id_foreign FOREIGN KEY (original_invoice_id) REFERENCES public.invoices(id);


--
-- Name: affiliates affiliates_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.affiliates
    ADD CONSTRAINT affiliates_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: cloudstack_environment_products cloudstack_environment_products_environment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environment_products
    ADD CONSTRAINT cloudstack_environment_products_environment_id_foreign FOREIGN KEY (environment_id) REFERENCES public.cloudstack_environments(id);


--
-- Name: cloudstack_environment_products cloudstack_environment_products_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_environment_products
    ADD CONSTRAINT cloudstack_environment_products_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: cloudstack_jobs cloudstack_jobs_vm_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_jobs
    ADD CONSTRAINT cloudstack_jobs_vm_subscription_id_foreign FOREIGN KEY (vm_subscription_id) REFERENCES public.cloudstack_vm_subscriptions(id);


--
-- Name: cloudstack_managerdomain_cloudstack_vm_ssh_keys cloudstack_managerdomain_cloudstack_vm_ssh_keys_manager_domain_; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_managerdomain_cloudstack_vm_ssh_keys_manager_domain_ FOREIGN KEY (manager_domain_deployment_id) REFERENCES public.cloudstack_managerdomain_subscriptions(id);


--
-- Name: cloudstack_managerdomain_cloudstack_vm_ssh_keys cloudstack_managerdomain_cloudstack_vm_ssh_keys_ssh_key_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_managerdomain_cloudstack_vm_ssh_keys_ssh_key_id_fore FOREIGN KEY (ssh_key_id) REFERENCES public.cloudstack_vm_ssh_keys(id);


--
-- Name: cloudstack_managerdomain_subscriptions cloudstack_managerdomain_subscriptions_environment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_subscriptions
    ADD CONSTRAINT cloudstack_managerdomain_subscriptions_environment_id_foreign FOREIGN KEY (environment_id) REFERENCES public.cloudstack_environments(id);


--
-- Name: cloudstack_vm_deployment_ssh_key cloudstack_vm_deployment_ssh_key_ssh_key_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_deployment_ssh_key
    ADD CONSTRAINT cloudstack_vm_deployment_ssh_key_ssh_key_id_foreign FOREIGN KEY (ssh_key_id) REFERENCES public.cloudstack_vm_ssh_keys(id);


--
-- Name: cloudstack_vm_deployment_ssh_key cloudstack_vm_deployment_ssh_key_vm_deployment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_deployment_ssh_key
    ADD CONSTRAINT cloudstack_vm_deployment_ssh_key_vm_deployment_id_foreign FOREIGN KEY (vm_deployment_id) REFERENCES public.cloudstack_vm_subscriptions(id);


--
-- Name: cloudstack_vm_ssh_keys cloudstack_vm_ssh_keys_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_ssh_keys
    ADD CONSTRAINT cloudstack_vm_ssh_keys_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: cloudstack_vm_subscriptions cloudstack_vm_subscriptions_subscription_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_subscriptions
    ADD CONSTRAINT cloudstack_vm_subscriptions_subscription_uuid_foreign FOREIGN KEY (subscription_uuid) REFERENCES public.subscriptions(uuid);


--
-- Name: cloudstack_volume_subscriptions cloudstack_volume_subscriptions_subscription_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_volume_subscriptions
    ADD CONSTRAINT cloudstack_volume_subscriptions_subscription_uuid_foreign FOREIGN KEY (subscription_uuid) REFERENCES public.subscriptions(uuid);


--
-- Name: cloudstack_managerdomain_subscriptions cs_managerdomain_subscription_subscription_uuid_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_managerdomain_subscriptions
    ADD CONSTRAINT cs_managerdomain_subscription_subscription_uuid_fk FOREIGN KEY (subscription_uuid) REFERENCES public.subscriptions(uuid);


--
-- Name: cloudstack_vm_subscriptions cs_vm_manager_domain_subscription_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_vm_subscriptions
    ADD CONSTRAINT cs_vm_manager_domain_subscription_id_fk FOREIGN KEY (manager_domain_subscription_id) REFERENCES public.cloudstack_managerdomain_subscriptions(id);


--
-- Name: cloudstack_volume_subscriptions cs_volume_manager_domain_subscription_id_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cloudstack_volume_subscriptions
    ADD CONSTRAINT cs_volume_manager_domain_subscription_id_fk FOREIGN KEY (manager_domain_subscription_id) REFERENCES public.cloudstack_managerdomain_subscriptions(id);


--
-- Name: customer_addresses customer_addresses_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_addresses
    ADD CONSTRAINT customer_addresses_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customer_contacts customer_contacts_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_contacts
    ADD CONSTRAINT customer_contacts_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: customer_customers customer_customers_child_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_customers
    ADD CONSTRAINT customer_customers_child_customer_id_foreign FOREIGN KEY (child_customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: customer_customers customer_customers_parent_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_customers
    ADD CONSTRAINT customer_customers_parent_customer_id_foreign FOREIGN KEY (parent_customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: one_time_services customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_services
    ADD CONSTRAINT customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE RESTRICT;


--
-- Name: customer_migrated_customer customer_migrated_customers_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_migrated_customer
    ADD CONSTRAINT customer_migrated_customers_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customer_migrated_customer customer_migrated_customers_migrated_customers_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_migrated_customer
    ADD CONSTRAINT customer_migrated_customers_migrated_customers_id_foreign FOREIGN KEY (migrated_customer_id) REFERENCES public.migrated_customers(id);


--
-- Name: customer_product_discount customer_product_discount_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_discount
    ADD CONSTRAINT customer_product_discount_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customer_product_discount customer_product_discount_product_discount_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_discount
    ADD CONSTRAINT customer_product_discount_product_discount_id_foreign FOREIGN KEY (product_discount_id) REFERENCES public.product_discounts(id);


--
-- Name: customer_product_group customer_product_group_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_group
    ADD CONSTRAINT customer_product_group_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: customer_product_group customer_product_group_product_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_product_group
    ADD CONSTRAINT customer_product_group_product_group_id_foreign FOREIGN KEY (product_group_id) REFERENCES public.product_groups(id) ON DELETE CASCADE;


--
-- Name: customer_vat_errors customer_vat_errors_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_vat_errors
    ADD CONSTRAINT customer_vat_errors_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: customer_wallets customer_wallets_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customer_wallets
    ADD CONSTRAINT customer_wallets_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: customers customers_partner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_partner_id_foreign FOREIGN KEY (partner_id) REFERENCES public.customers(id);


--
-- Name: dns_customer_templates dns__customer_templates_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_templates
    ADD CONSTRAINT dns__customer_templates_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE CASCADE;


--
-- Name: dns_template_record_set_rows dns__template_record_set_rows_template_record_set_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_set_rows
    ADD CONSTRAINT dns__template_record_set_rows_template_record_set_id_foreign FOREIGN KEY (template_record_set_id) REFERENCES public.dns_template_record_sets(id) ON DELETE CASCADE;


--
-- Name: dns_template_record_sets dns__template_record_sets_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_template_record_sets
    ADD CONSTRAINT dns__template_record_sets_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.dns_templates(id) ON DELETE CASCADE;


--
-- Name: dns_customer_template_records dns_customer_template_records_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_customer_template_records
    ADD CONSTRAINT dns_customer_template_records_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.dns_customer_templates(id) ON DELETE CASCADE;


--
-- Name: dns_deployment_dns_nameserver dns_deployment_dns_nameserver_dns_deployment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployment_dns_nameserver
    ADD CONSTRAINT dns_deployment_dns_nameserver_dns_deployment_id_foreign FOREIGN KEY (dns_deployment_id) REFERENCES public.dns_deployments(id);


--
-- Name: dns_deployment_dns_nameserver dns_deployment_dns_nameserver_dns_nameserver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployment_dns_nameserver
    ADD CONSTRAINT dns_deployment_dns_nameserver_dns_nameserver_id_foreign FOREIGN KEY (dns_nameserver_id) REFERENCES public.dns_nameservers(id);


--
-- Name: dns_deployments dns_deployments_subscription_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_deployments
    ADD CONSTRAINT dns_deployments_subscription_uuid_foreign FOREIGN KEY (subscription_uuid) REFERENCES public.subscriptions(uuid);


--
-- Name: dns_external_nameservers dns_external_nameservers_dns_deployment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_external_nameservers
    ADD CONSTRAINT dns_external_nameservers_dns_deployment_id_foreign FOREIGN KEY (dns_deployment_id) REFERENCES public.dns_deployments(id);


--
-- Name: dns_nameservers dns_nameservers_dns_region_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameservers
    ADD CONSTRAINT dns_nameservers_dns_region_id_foreign FOREIGN KEY (dns_region_id) REFERENCES public.dns_regions(id);


--
-- Name: dns_nameserver_domain_subscription dns_ns_domain_subscription_dns_nameserver_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameserver_domain_subscription
    ADD CONSTRAINT dns_ns_domain_subscription_dns_nameserver_id_foreign FOREIGN KEY (dns_nameserver_id) REFERENCES public.dns_nameservers(id);


--
-- Name: dns_nameserver_domain_subscription dns_ns_domain_subscription_domain_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_nameserver_domain_subscription
    ADD CONSTRAINT dns_ns_domain_subscription_domain_subscription_id_foreign FOREIGN KEY (domain_subscription_id) REFERENCES public.domain_subscriptions(id);


--
-- Name: dns_record_changes dns_record_changes_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dns_record_changes
    ADD CONSTRAINT dns_record_changes_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: domain_contacts domain__contacts_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contacts
    ADD CONSTRAINT domain__contacts_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: domain_subscriptions domain__subscriptions_contact_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions
    ADD CONSTRAINT domain__subscriptions_contact_owner_id_foreign FOREIGN KEY (contact_owner_id) REFERENCES public.domain_contacts(id);


--
-- Name: domain_subscriptions domain__subscriptions_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions
    ADD CONSTRAINT domain__subscriptions_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.dns_customer_templates(id);


--
-- Name: domain_contact_provider domain_contact_provider_domain_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_provider
    ADD CONSTRAINT domain_contact_provider_domain_contact_id_foreign FOREIGN KEY (domain_contact_id) REFERENCES public.domain_contacts(id);


--
-- Name: domain_contact_provider domain_contact_provider_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_contact_provider
    ADD CONSTRAINT domain_contact_provider_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: domain_provider_status domain_provider_status_domain_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_provider_status
    ADD CONSTRAINT domain_provider_status_domain_subscription_id_foreign FOREIGN KEY (domain_subscription_id) REFERENCES public.domain_subscriptions(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: domain_provider_status domain_provider_status_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_provider_status
    ADD CONSTRAINT domain_provider_status_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: domain_provider_status domain_provider_status_rtr_response_log_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_provider_status
    ADD CONSTRAINT domain_provider_status_rtr_response_log_id_foreign FOREIGN KEY (rtr_response_log_id) REFERENCES public.rtr_response_log(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: domain_subscriptions domain_subscriptions_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.domain_subscriptions
    ADD CONSTRAINT domain_subscriptions_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: email_history email_history_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_history
    ADD CONSTRAINT email_history_template_id_foreign FOREIGN KEY (template_id) REFERENCES public.templates(id);


--
-- Name: hosting_subscriptions hosting__subscriptions_basekit_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting__subscriptions_basekit_server_id_foreign FOREIGN KEY (basekit_server_id) REFERENCES public.hosting_servers(id);


--
-- Name: hosting_subscriptions hosting__subscriptions_mail_only_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting__subscriptions_mail_only_server_id_foreign FOREIGN KEY (mail_only_server_id) REFERENCES public.hosting_servers(id);


--
-- Name: hosting_product_compositions hosting_product_compositions_composed_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_composed_product_id_foreign FOREIGN KEY (composed_product_id) REFERENCES public.products(id);


--
-- Name: hosting_product_compositions hosting_product_compositions_mail_only_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_mail_only_product_id_foreign FOREIGN KEY (mail_only_product_id) REFERENCES public.products(id);


--
-- Name: hosting_product_compositions hosting_product_compositions_web_only_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_web_only_product_id_foreign FOREIGN KEY (web_only_product_id) REFERENCES public.products(id);


--
-- Name: hosting_product_compositions hosting_product_compositions_wp_composed_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_wp_composed_product_id_foreign FOREIGN KEY (wp_composed_product_id) REFERENCES public.products(id);


--
-- Name: hosting_product_compositions hosting_product_compositions_wp_web_only_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_product_compositions
    ADD CONSTRAINT hosting_product_compositions_wp_web_only_product_id_foreign FOREIGN KEY (wp_web_only_product_id) REFERENCES public.products(id);


--
-- Name: hosting_subscriptions hosting_subscriptions_mail_only_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting_subscriptions_mail_only_provider_id_foreign FOREIGN KEY (mail_only_provider_id) REFERENCES public.providers(id);


--
-- Name: hosting_subscriptions hosting_subscriptions_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting_subscriptions_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: hosting_subscriptions hosting_subscriptions_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting_subscriptions_server_id_foreign FOREIGN KEY (server_id) REFERENCES public.hosting_servers(id) ON DELETE CASCADE;


--
-- Name: hosting_subscriptions hosting_subscriptions_sitebuilder_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting_subscriptions_sitebuilder_provider_id_foreign FOREIGN KEY (sitebuilder_provider_id) REFERENCES public.providers(id);


--
-- Name: hosting_subscriptions hosting_subscriptions_spam_experts_cluster_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hosting_subscriptions
    ADD CONSTRAINT hosting_subscriptions_spam_experts_cluster_id_foreign FOREIGN KEY (spam_experts_cluster_id) REFERENCES public.spam_experts_clusters(id);


--
-- Name: hubspot_events hubspot_events_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hubspot_events
    ADD CONSTRAINT hubspot_events_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: one_time_service_invoice invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_service_invoice
    ADD CONSTRAINT invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES public.invoices(id) ON DELETE RESTRICT;


--
-- Name: invoices invoices_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: invoices invoices_merge_on_pdf_with_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_merge_on_pdf_with_invoice_id_foreign FOREIGN KEY (merge_on_pdf_with_invoice_id) REFERENCES public.invoices(id);


--
-- Name: invoices invoices_parent_invoice_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_parent_invoice_id_foreign FOREIGN KEY (parent_invoice_id) REFERENCES public.invoices(id);


--
-- Name: invoices invoices_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: invoices invoices_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: label_subscription label_subscription_label_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_subscription
    ADD CONSTRAINT label_subscription_label_id_foreign FOREIGN KEY (label_id) REFERENCES public.labels(id);


--
-- Name: label_subscription label_subscription_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.label_subscription
    ADD CONSTRAINT label_subscription_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: labels labels_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.labels
    ADD CONSTRAINT labels_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: mandate_migrated_customer mandate_migrated_customer_mandate_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandate_migrated_customer
    ADD CONSTRAINT mandate_migrated_customer_mandate_id_foreign FOREIGN KEY (mandate_id) REFERENCES public.mandates(id);


--
-- Name: mandate_migrated_customer mandate_migrated_customer_migrated_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandate_migrated_customer
    ADD CONSTRAINT mandate_migrated_customer_migrated_customer_id_foreign FOREIGN KEY (migrated_customer_id) REFERENCES public.migrated_customers(id);


--
-- Name: mandates mandates_mollie_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mandates
    ADD CONSTRAINT mandates_mollie_customer_id_foreign FOREIGN KEY (mollie_customer_id) REFERENCES public.mollie_customers(id);


--
-- Name: microsoft365_customer_info microsoft365_customer_info_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_customer_info
    ADD CONSTRAINT microsoft365_customer_info_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: microsoft365_http_log microsoft365_http_log_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_http_log
    ADD CONSTRAINT microsoft365_http_log_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: microsoft365_sync_log microsoft365_sync_log_microsoft365_customer_info_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_sync_log
    ADD CONSTRAINT microsoft365_sync_log_microsoft365_customer_info_id_foreign FOREIGN KEY (microsoft365_customer_info_id) REFERENCES public.microsoft365_customer_info(id);


--
-- Name: microsoft365_sync_log microsoft365_sync_log_microsoft365_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_sync_log
    ADD CONSTRAINT microsoft365_sync_log_microsoft365_subscription_id_foreign FOREIGN KEY (microsoft365_subscription_id) REFERENCES public.microsoft365_subscriptions(id);


--
-- Name: migrated_customer_migrated_subscription migrated_custs_mig_subs_ubi_migrated_cust_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_customer_migrated_subscription
    ADD CONSTRAINT migrated_custs_mig_subs_ubi_migrated_cust_id_foreign FOREIGN KEY (migrated_customer_id) REFERENCES public.migrated_customers(id);


--
-- Name: migrated_customer_migrated_subscription migrated_custs_mig_subs_ubi_migrated_subs_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_customer_migrated_subscription
    ADD CONSTRAINT migrated_custs_mig_subs_ubi_migrated_subs_id_foreign FOREIGN KEY (migrated_subscription_id) REFERENCES public.migrated_subscriptions(id);


--
-- Name: migrated_dns_template_records migrated_dns_template_records_dns_customer_template_record_id_f; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_template_records
    ADD CONSTRAINT migrated_dns_template_records_dns_customer_template_record_id_f FOREIGN KEY (dns_customer_template_record_id) REFERENCES public.dns_customer_template_records(id);


--
-- Name: migrated_dns_template_records migrated_dns_template_records_migrated_dns_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_template_records
    ADD CONSTRAINT migrated_dns_template_records_migrated_dns_template_id_foreign FOREIGN KEY (migrated_dns_template_id) REFERENCES public.migrated_dns_templates(id);


--
-- Name: migrated_dns_templates migrated_dns_templates_dns_customer_template_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_templates
    ADD CONSTRAINT migrated_dns_templates_dns_customer_template_id_foreign FOREIGN KEY (dns_customer_template_id) REFERENCES public.dns_customer_templates(id);


--
-- Name: migrated_dns_templates migrated_dns_templates_migrated_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_dns_templates
    ADD CONSTRAINT migrated_dns_templates_migrated_customer_id_foreign FOREIGN KEY (migrated_customer_id) REFERENCES public.migrated_customers(id);


--
-- Name: migrated_subscription_steps migrated_subscription_steps_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscription_steps
    ADD CONSTRAINT migrated_subscription_steps_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: migrated_subscription_subscription migrated_subscriptions_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscription_subscription
    ADD CONSTRAINT migrated_subscriptions_id_foreign FOREIGN KEY (migrated_subscription_id) REFERENCES public.migrated_subscriptions(id);


--
-- Name: mollie_customers mollie_customers_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mollie_customers
    ADD CONSTRAINT mollie_customers_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: notes notes_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: notes notes_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: microsoft365_kpn_product office365__kpn__product_product_price_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_kpn_product
    ADD CONSTRAINT office365__kpn__product_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id);


--
-- Name: microsoft365_subscriptions office365__subscriptions_office365_customer_info_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions
    ADD CONSTRAINT office365__subscriptions_office365_customer_info_id_foreign FOREIGN KEY (microsoft365_customer_info_id) REFERENCES public.microsoft365_customer_info(id);


--
-- Name: microsoft365_subscriptions office365__subscriptions_parent_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.microsoft365_subscriptions
    ADD CONSTRAINT office365__subscriptions_parent_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: one_time_service_invoice one_time_service_invoice_one_time_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_service_invoice
    ADD CONSTRAINT one_time_service_invoice_one_time_service_id_foreign FOREIGN KEY (one_time_service_id) REFERENCES public.one_time_services(id) ON DELETE RESTRICT;


--
-- Name: order_line_items order_line_items_one_time_service_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_items
    ADD CONSTRAINT order_line_items_one_time_service_id_foreign FOREIGN KEY (one_time_service_id) REFERENCES public.one_time_services(id);


--
-- Name: order_line_items order_line_items_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_items
    ADD CONSTRAINT order_line_items_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id);


--
-- Name: order_line_items order_line_items_parent_subscription_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.order_line_items
    ADD CONSTRAINT order_line_items_parent_subscription_uuid_foreign FOREIGN KEY (parent_subscription_uuid) REFERENCES public.subscriptions(uuid);


--
-- Name: orders orders_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.orders
    ADD CONSTRAINT orders_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: parent_product parent_product_parent_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parent_product
    ADD CONSTRAINT parent_product_parent_product_id_foreign FOREIGN KEY (parent_product_id) REFERENCES public.products(id);


--
-- Name: parent_product parent_product_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parent_product
    ADD CONSTRAINT parent_product_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: payments payments_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_order_id_foreign FOREIGN KEY (order_id) REFERENCES public.orders(id);


--
-- Name: product_allowed_changes product_allowed_changes_from_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_allowed_changes
    ADD CONSTRAINT product_allowed_changes_from_product_id_foreign FOREIGN KEY (from_product_id) REFERENCES public.products(id);


--
-- Name: product_allowed_changes product_allowed_changes_to_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_allowed_changes
    ADD CONSTRAINT product_allowed_changes_to_product_id_foreign FOREIGN KEY (to_product_id) REFERENCES public.products(id);


--
-- Name: product_coupling product_coupling_child_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_coupling
    ADD CONSTRAINT product_coupling_child_product_id_foreign FOREIGN KEY (child_product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: product_coupling product_coupling_conditional_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_coupling
    ADD CONSTRAINT product_coupling_conditional_product_id_foreign FOREIGN KEY (conditional_product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: product_discounts product_discounts_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_discounts
    ADD CONSTRAINT product_discounts_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: one_time_services product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_services
    ADD CONSTRAINT product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE RESTRICT;


--
-- Name: product_introduction_discounts product_introduction_discounts_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_introduction_discounts
    ADD CONSTRAINT product_introduction_discounts_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: product_price_alternatives product_price_alternatives_alternative_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price_alternatives
    ADD CONSTRAINT product_price_alternatives_alternative_product_id_foreign FOREIGN KEY (alternative_product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: product_price_alternatives product_price_alternatives_product_price_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_price_alternatives
    ADD CONSTRAINT product_price_alternatives_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id) ON DELETE CASCADE;


--
-- Name: product_prices product_prices_product_discount_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_discount_id_foreign FOREIGN KEY (product_discount_id) REFERENCES public.product_discounts(id);


--
-- Name: product_prices product_prices_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: product_prices product_prices_translation_key_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_prices
    ADD CONSTRAINT product_prices_translation_key_id_foreign FOREIGN KEY (translation_key_id) REFERENCES public.translation_keys(id);


--
-- Name: product_promotions product_promotions_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_promotions
    ADD CONSTRAINT product_promotions_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id) ON DELETE CASCADE;


--
-- Name: product_specs product_specs_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.product_specs
    ADD CONSTRAINT product_specs_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: products products_product_group_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_product_group_id_foreign FOREIGN KEY (product_group_id) REFERENCES public.product_groups(id);


--
-- Name: products products_requirements_translation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_requirements_translation_id_foreign FOREIGN KEY (requirements_translation_id) REFERENCES public.translation_keys(id);


--
-- Name: provider_settings provider_settings_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provider_settings
    ADD CONSTRAINT provider_settings_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: provisioning_results provisioning_results_request_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.provisioning_results
    ADD CONSTRAINT provisioning_results_request_id_foreign FOREIGN KEY (request_id) REFERENCES public.provisioning_requests(id);


--
-- Name: reseller_hosting_subscriptions reseller_hosting_subscriptions_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT reseller_hosting_subscriptions_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: reseller_hosting_subscriptions reseller_hosting_subscriptions_server_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reseller_hosting_subscriptions
    ADD CONSTRAINT reseller_hosting_subscriptions_server_id_foreign FOREIGN KEY (server_id) REFERENCES public.hosting_servers(id);


--
-- Name: ssl_subscriptions ssl_subscriptions_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ssl_subscriptions
    ADD CONSTRAINT ssl_subscriptions_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.providers(id);


--
-- Name: one_time_services subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.one_time_services
    ADD CONSTRAINT subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE RESTRICT;


--
-- Name: subscription_mutations subscription_mutations_product_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_mutations
    ADD CONSTRAINT subscription_mutations_product_id_foreign FOREIGN KEY (product_id) REFERENCES public.products(id);


--
-- Name: subscription_mutations subscription_mutations_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_mutations
    ADD CONSTRAINT subscription_mutations_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: subscription_transfer subscription_transfer_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_transfer
    ADD CONSTRAINT subscription_transfer_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: subscription_transfer subscription_transfer_transfer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_transfer
    ADD CONSTRAINT subscription_transfer_transfer_id_foreign FOREIGN KEY (transfer_id) REFERENCES public.transfers(id);


--
-- Name: subscriptions subscriptions_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id);


--
-- Name: migrated_subscription_subscription subscriptions_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrated_subscription_subscription
    ADD CONSTRAINT subscriptions_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: subscriptions subscriptions_parent_subscription_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_parent_subscription_id_foreign FOREIGN KEY (parent_subscription_id) REFERENCES public.subscriptions(id);


--
-- Name: subscriptions subscriptions_product_price_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_product_price_id_foreign FOREIGN KEY (product_price_id) REFERENCES public.product_prices(id);


--
-- Name: subscriptions subscriptions_product_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_product_uuid_foreign FOREIGN KEY (product_uuid) REFERENCES public.products(uuid);


--
-- Name: transfers transfers_from_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_from_customer_id_foreign FOREIGN KEY (from_customer_id) REFERENCES public.customers(id);


--
-- Name: transfers transfers_to_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transfers
    ADD CONSTRAINT transfers_to_customer_id_foreign FOREIGN KEY (to_customer_id) REFERENCES public.customers(id);


--
-- Name: translation_strings translation_strings_key_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_strings
    ADD CONSTRAINT translation_strings_key_id_foreign FOREIGN KEY (key_id) REFERENCES public.translation_keys(id) ON DELETE CASCADE;


--
-- Name: translation_strings translation_strings_language_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.translation_strings
    ADD CONSTRAINT translation_strings_language_id_foreign FOREIGN KEY (language_id) REFERENCES public.translation_languages(id) ON DELETE CASCADE;


--
-- Name: voucher_claims voucher_claims_order_line_item_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voucher_claims
    ADD CONSTRAINT voucher_claims_order_line_item_id_foreign FOREIGN KEY (order_line_item_id) REFERENCES public.order_line_items(id);


--
-- Name: voucher_claims voucher_claims_voucher_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.voucher_claims
    ADD CONSTRAINT voucher_claims_voucher_id_foreign FOREIGN KEY (voucher_id) REFERENCES public.vouchers(id);


--
-- Name: vouchers vouchers_product_group_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vouchers
    ADD CONSTRAINT vouchers_product_group_uuid_foreign FOREIGN KEY (product_group_uuid) REFERENCES public.product_groups(uuid);


--
-- Name: vouchers vouchers_product_uuid_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vouchers
    ADD CONSTRAINT vouchers_product_uuid_foreign FOREIGN KEY (product_uuid) REFERENCES public.products(uuid);


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3
-- Dumped by pg_dump version 16.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	2000_01_01_000000_create_customers_table	1
2	2000_01_01_000000_create_password_resets_table	1
3	2000_01_01_000001_create_users_table	1
4	2016_05_16_012539_create_templates_table	1
5	2016_11_01_081328_create_new_password_table	1
6	2017_06_19_112120_create_jobs_table	1
7	2017_06_19_112207_create_failed_jobs_table	1
8	2018_01_01_000000_create_action_events_table	1
9	2018_09_01_162423_create_hosting_providers_table	1
10	2018_11_19_160237_create_subscriptions_table	1
11	2018_11_27_132954_create_servers_table	1
12	2018_11_27_144026_create_hosting_subscriptions_table	1
13	2018_12_04_084816_create_domain_subscriptions_table	1
14	2018_12_11_120352_create_customer_addresses_table	1
15	2018_12_12_144026_create_ssl_subscriptions_table	1
16	2019_02_06_115810_create_audits_table	1
17	2019_02_28_090756_create_product_groups_table	1
18	2019_03_01_161228_create_products_table	1
19	2019_03_04_114238_create_product_specs_table	1
20	2019_03_04_114614_create_product_prices_table	1
21	2019_03_11_140704_create_invoices_table	1
22	2019_04_09_095544_create_customer_product_group_pivot_table	1
23	2019_04_11_103001_create_orders_table	1
24	2019_04_15_133613_create_ghost_login_tokens_table	1
25	2019_05_03_114136_create_customer_contacts_table	1
26	2019_05_10_000000_add_fields_to_action_events_table	1
27	2019_05_20_133353_create_payments_table	1
28	2019_06_12_100259_add_p_h_p_version_to_server	1
29	2019_06_17_091943_create_permission_tables	1
30	2019_07_17_143921_remove_unique_constraint_hosting_subscription	1
31	2019_07_23_113923_add_hosting_is_free_to_server	1
32	2019_10_21_090102_add_type_field_to_hosting__servers_table	1
33	2019_10_23_115118_add_name_to_hosting_servers_table	1
34	2019_12_30_131843_add_dns_template	1
35	2020_01_02_083335_make-dns-template-slug-unique	1
36	2020_01_14_211420_create_customer_customers_table	1
37	2020_01_18_131843_add_dns_customer_templates	1
38	2020_01_18_131843_add_dns_customer_templates_records	1
39	2020_08_20_124707_add_parent_subscription_id	1
40	2020_08_20_164353_create_addons_table	1
41	2020_08_31_145157_create_vouchers_table	1
42	2020_08_31_162330_create_voucher_customer_table	1
43	2020_08_31_162345_create_voucher_transactions_table	1
44	2020_09_02_145209_create_ssl_providers_table	1
45	2020_09_02_150659_update_ssl_subscriptions_table	1
46	2020_09_05_115118_add_directadmin_username_to_hosting_subscription_table	1
47	2020_09_05_115118_add_password_to_hosting_servers_table	1
48	2020_09_05_115118_add_use_ssl_to_hosting_servers_table	1
49	2020_09_07_131255_add_product_discounts_table	1
50	2020_09_07_131351_add_customer_product_discount_table	1
51	2020_09_08_133612_add_product_discount_id_column	1
52	2020_09_30_173224_add_ftps_host_to_hosting_subscriptions_table	1
53	2020_09_30_173225_create_hosting__upgrades_table	1
54	2020_09_30_173225_create_hosting_sanity_table	1
55	2020_10_14_100026_alter_ssl_subscriptions_table	1
56	2020_10_14_162226_create_ssl_sanity_table	1
57	2020_10_14_164559_add_custom_csr_to_ssl__subscriptions_table	1
58	2020_10_20_110831_add_request_identifier_to_ssl_subscriptions	1
59	2020_11_13_173224_add_last_created_to_hosting_subscriptions_table	1
60	2020_11_17_142551_create_product_upgrade_table	1
61	2020_11_24_094354_create_discount_voucher_view	1
62	2020_12_04_084816_create_domainblacklist_table	1
63	2020_12_06_084816_create_domain_providers_table	1
64	2020_12_17_084816_update_domain_subscriptions_table	1
65	2020_12_19_105625_add_last_result_to_domain_subscriptions_table	1
66	2021_01_18_010101_create_affiliates_table	1
67	2021_01_18_010102_create_affiliates_customers_table	1
68	2021_01_21_010101_create_affiliate_transactions_table	1
69	2021_01_26_010101_create_affiliate_product_groups_pivot_table	1
70	2021_01_26_110020_add_product_group_rates	1
71	2021_01_29_000001_add_template_id_to_domain_subscriptions_table	1
72	2021_02_02_000001_create_domain_contacts_table	1
73	2021_02_02_000002_add_contact_owner_id_to_domain_subscriptions_table	1
74	2021_02_03_134637_add_vat_rate_number_to_customers	1
75	2021_02_05_000001_create_domain_contacts_provider_pivot_table	1
76	2021_03_01_150620_create_transfers_table	1
77	2021_03_01_150621_create_transfers_subscriptions_pivot_table	1
78	2021_03_09_000001_add_reason_failed_column_to_transfers_table	1
79	2021_03_17_145327_add-cancelation-date-to-subscriptions	1
80	2021_03_29_000001_add_product_id_to_product_discounts_table	1
81	2021_04_12_000001_create_product_coupling_pivot_table	1
82	2021_04_13_104823_create_mail_only_providers_table	1
83	2021_04_20_173224_add_mail_only_provider_id_to_hosting_subscriptions_table	1
84	2021_04_26_163342_create_sitebuilder_providers_table	1
85	2021_04_26_164352_add_sitebuilder_provider_id_to_hostings__subscription_table	1
86	2021_05_11_111437_add_wordpress_to_hosting_servers_table	1
87	2021_05_11_215300_add_basekit_site_ref_to_hosting_subscription_table	1
88	2021_05_12_173224_add_has_wordpress_to_hosting_subscriptions_table	1
89	2021_05_17_164300_add_basekit_server_id_to_hosting_subscription_table	1
90	2021_05_20_104600_add_server_id_to_sitebuilder_provider	1
91	2021_05_20_144600_update_server_id_on_hosting_subscriptions_table	1
92	2021_06_14_000000_add_product_slug	1
93	2021_07_27_140012_add_slug_to_templates_table	1
94	2021_08_25_193039_create_nova_notifications_table	1
95	2021_09_02_092347_create_cloudstack	1
96	2021_09_10_173224_add_has_valid_sso_call_to_hosting_subscriptions_table	1
97	2021_09_15_160332_create_reseller_hosting_subscriptions_table	1
98	2021_10_18_115154_create_languages_table	1
99	2021_10_18_115707_create_translation_keys_table	1
100	2021_10_18_115809_create_translation_strings_table	1
101	2021_10_27_140806_customer_and_invoice_vat_rate_update	1
102	2021_11_14_104047_remove_cloudstack_product_type	1
103	2021_11_14_112357_add_cloudstack_environment_fields	1
104	2021_11_16_164418_add_column_users_language	1
105	2021_12_06_095959_rename_invoice_processed_column	1
106	2021_12_07_142434_alter_column_translation_keys_context_to_nullable	1
107	2021_12_08_120558_rename_languages_table_to_translation_languages	1
108	2021_12_09_115852_drop-column-translation-key-english-translation	1
109	2021_12_13_173752_enlarge_hosting_servers_fields	1
110	2021_12_16_085912_add_productgroup_slug_unique_index	1
111	2021_12_27_115809_create_customer_vat_table	1
112	2021_12_29_082247_add_column_announced_by_harbor_at_to_invoices	1
113	2022_01_10_115348_add_column_vps_os_id_to_order_line_items	1
114	2022_01_27_135449_drop_column_eat_resource_uuid_hosting_subscription	1
115	2022_02_04_125110_alter_users_column_telephone_to_nullable	1
116	2022_02_10_104835_create_office365__subscriptions	1
117	2022_02_12_114021_rename_order_line_item_vps_os_id	1
118	2022_02_15_160345_change_vps_os_uuid_type	1
119	2022_02_15_164532_alter_table_office365__subscriptions_change_kpn_customer_id	1
120	2022_02_22_101000_create_dns_regions_table	1
121	2022_02_22_101040_create_dns_nameservers_table	1
122	2022_02_22_105905_create_dns_nameserver_domain_subscription_table	1
123	2022_03_01_160333_rename_office365__subscriptions	1
124	2022_03_03_135434_create_office365_kpn_product_table	1
125	2022_03_03_155433_add_column_status_office365__subscriptions	1
126	2022_03_03_163828_office_add_tenant_name_office365_customer_info	1
127	2022_03_08_095546_add_office_customer_info_id_to_office_subscriptions_table	1
128	2022_03_14_115223_add_customer_anonymized_at_column	1
129	2022_03_18_161800_add_payt_admin_url_to_customer	1
130	2022_03_22_150436_rename_parent_subscription_id	1
131	2022_03_23_125600_update_technical_status	1
132	2022_03_30_103140_alter_customer_contact_table	1
133	2022_04_01_095750_create_migrated_customers_table	1
134	2022_04_11_131028_add_products_foreign_key_to_subscriptions	1
135	2022_04_11_132313_add_product_groups_foreign_key_to_subscriptions	1
136	2022_04_20_141531_update_columns_office365_customer_info	1
137	2022_04_26_000000_add_fields_to_nova_notifications_table	1
138	2022_04_28_112500_customer_addresses_update_province_nullable	1
139	2022_04_28_145356_add_product_prices_foreign_key_to_subscriptions	1
140	2022_05_02_132022_create_microsoft365_log_table	1
141	2022_05_04_033115_create_telescope_entries_table	1
142	2022_05_09_145421_rename_office_to_microsoft	1
143	2022_05_10_114952_add_xml_root_name_to_microsoft_365_log	1
144	2022_05_11_130209_product_price_billing_period	1
145	2022_05_11_142831_add-tenant_id-to-microsoft365-customer-info-table	1
146	2022_05_12_134745_contract_period	1
147	2022_05_17_110552_rename_field_product_upgrade_id	1
148	2022_05_18_144225_add_extra_successful_columns_customer_migration	1
149	2022_05_23_122405_hosting_provider_slug_and_driver_non_nullable	1
150	2022_05_23_133406_domain_provider_slug_and_driver_non_nullable	1
151	2022_05_23_143712_ssl_provider_slug_and_driver_non_nullable	1
152	2022_05_23_153825_add_migrated_at_column_customer_migration	1
153	2022_05_24_095612_rename_table_hosting__providers	1
154	2022_05_24_095823_rename_table_hosting__servers	1
155	2022_05_24_100625_rename_table_hosting__subscriptions	1
156	2022_05_24_110302_rename_table_cloudstack__environments	1
157	2022_05_24_110453_rename_table_cloudstack__managerdomain_subscriptions	1
158	2022_05_24_110746_rename_table_cloudstack__vm_subscriptions	1
159	2022_05_24_110954_rename_table_cloudstack__volume_subscriptions	1
160	2022_05_24_111212_rename_table_cloudstack__environment_products	1
161	2022_05_24_111742_rename_table_reseller_hosting__subscriptions	1
162	2022_05_24_112221_rename_table_dns__regions	1
163	2022_05_24_112258_rename_table_dns__nameservers	1
164	2022_05_24_131928_rename_table_ssl__sanity	1
165	2022_05_24_132100_rename_table_dns__nameserver_domain__subscription	1
166	2022_05_24_141759_add_translation_key_foreign_key_to_product_prices	1
167	2022_05_24_145012_rename_table_domain__subscriptions	1
168	2022_05_24_162314_rename_table_ssl__subscriptions	1
169	2022_05_24_163404_rename_table_dns__templates	1
170	2022_05_24_163543_rename_table_dns__customer_template_records	1
171	2022_05_24_171050_rename_table_dns__customer_templates	1
172	2022_05_24_171405_rename_table_ssl__providers	1
173	2022_05_24_171503_rename_table_hosting__upgrades	1
174	2022_05_24_171538_rename_table_hosting__sanity	1
175	2022_05_24_172022_rename_table_domain__blacklist	1
176	2022_05_24_172101_rename_table_domain__providers	1
177	2022_05_24_172140_rename_table_domain__contacts	1
178	2022_05_25_111939_create_notes_table	1
179	2022_05_30_105704_subscription_to_product_fk_non_nullable	1
180	2022_06_08_163159_add_migrated_customer_dns_successful_column	1
181	2022_06_14_084403_remove_subscriptions_product_groups_relation	1
182	2022_06_14_141135_add_tenant_access_verified_boolean	1
183	2022_06_15_093958_add_invoice_parent_id	1
184	2022_06_15_140907_rename_hosting_upgrades_to_hosting_changes	1
185	2022_06_15_140907_rename_product_upgrades_to_product_changes	1
186	2022_06_16_132913_remove_nullable_contract_period	1
187	2022_06_20_154625_add_domain_categories	1
188	2022_06_20_172503_add_requirements_translation_id_foreign_key_to_products	1
189	2022_06_22_135302_remove_product_name_column_subscriptions	1
190	2022_06_27_105701_rename_columns_microsoft365_http_logs	1
191	2022_06_27_134548_remove_unique_customer_id_microsoft_365_customer_info	1
192	2022_06_29_091310_add_user_id_to_order	1
193	2022_07_12_133250_orderlineitem-billing-contract	1
194	2022_07_14_095921_subscription-unique-index	1
195	2022_07_26_172638_remove_user_image_columns	1
196	2022_08_03_184105_remove_user_birthday_column	1
197	2022_08_09_105138_create_migrated_subscriptions	1
198	2022_08_09_114537_remove_white_label_from_customer_table	1
199	2022_08_19_174735_remove_user_telephone_column	1
200	2022_08_29_110714_subscription_periods	1
201	2022_08_29_204325_remove_order_line_item_period	1
202	2022_08_30_133830_add_subscription_next_billing_date	1
203	2022_09_08_104538_product_price_periods_check_constraints	1
204	2022_09_14_143826_add_migrate_to_ssl_providers	1
205	2022_09_15_110649_create_hubspot_events_table	1
206	2022_09_20_133753_solve_migrated_subscriptions_foreign_keys	1
207	2022_09_27_214737_product_group_slug_non_nullable	1
208	2022_10_11_132208_create_job_batches_table	1
209	2022_10_12_085211_customer_credit_limit_positive	1
210	2022_10_12_104807_introduction_prices	1
211	2022_10_12_115107_product-price-non-negative	1
212	2022_10_13_073829_domain-subscription-provider-relation-mandatory	1
213	2022_10_20_145051_customer_number_non_nullable	1
214	2022_10_24_121200_add_customer_wallets_table	1
215	2022_10_28_101932_drop_customer_contact_email_unique	1
216	2022_10_28_112434_drop_user_email_unique_index	1
217	2022_11_08_131653_create_addon_providers_table	1
218	2022_11_08_151853_create_parent_product_table	1
219	2022_11_10_081345_domain-provider-remove-driver	1
220	2022_11_10_111143_add_latest_to_hubspot_events	1
221	2022_11_10_220737_ssl_subscription_provider_id_non_nullable	1
222	2022_11_21_152247_add_mail_configuration_columns_to_products_table	1
223	2022_11_23_100347_add_enable_invoicing_to_migrated_customers	1
224	2022_12_01_142348_add_parent_subscription_id_to_microsoft365_http_log	1
225	2022_12_07_090821_add_suspended_at_to_subscriptions_table	1
226	2022_12_07_100510_change_length_administrative_status_subscriptions_table	1
227	2022_12_19_000000_create_field_attachments_table	1
228	2022_12_20_110337_create_cloudstack_jobs_table	1
229	2022_12_20_131345_ssl-provider-remove-driver	1
230	2022_12_21_081345_hosting-provider-remove-driver	1
231	2022_12_21_102413_make_vm_cloudstack_id_nullable	1
232	2023_01_04_144442_table_servers_drop_wordpress_column	1
233	2023_01_04_144901_table_hosting_subscriptions__drop_wordpress_column	1
234	2023_01_11_095249_add_parent_id_to_order_line_items	1
235	2023_01_19_104524_remove_parent_id_on_order_line_items	1
236	2023_01_19_104824_add_parent_subscription_uuid_to_order_line_items	1
237	2023_02_17_095200_rename_customer_migrated_customers	1
238	2023_03_09_170547_create_migration_state_view	1
239	2023_03_09_170548_create_migration_invoicing_state_view	1
240	2023_03_27_181848_user_make_customer_id_not_null	1
241	2023_04_05_113000_create_index_invoices_subscription_uuid	1
242	2023_04_05_113100_create_index_order_line_items_subscription_uuid	1
243	2023_04_06_153645_drop_unique_product_id_table_products_introduction_price	1
244	2023_04_19_113845_add_user_uuid	1
245	2023_04_20_121854_add_timestamps_customer_discount_vouchers	1
246	2023_04_26_090411_add_orderable_prices	1
247	2023_05_02_164838_add_kpn_start_date_to_microsoft365_subscriptions	1
248	2023_05_02_164934_add_synced_at_to_microsoft365_customer_info	1
249	2023_05_04_140011_create_microsoft365_sync_logs_table	1
250	2023_05_04_173430_create_email_history_table	1
251	2023_05_08_161334_add_column_to_template_table	1
252	2023_05_17_140011_create_cancelations_agrigate_view	1
253	2023_05_17_140012_create_cancelations_individual_subscription_view	1
254	2023_06_08_140012_update_cancelations_individual_subscription_view	1
255	2023_06_14_140013_add_order_state	1
256	2023_07_07_120014_hubspot_events_add_index	1
257	2023_07_12_130014_add_customer_payment_type_is_verified	1
258	2023_07_27_171039_create_mollie_customer_table	1
259	2023_08_02_134239_create_mandates_table	1
260	2023_08_07_152334_mandates_add_payt_mandate_reference_id	1
261	2023_08_14_092407_add_order_line_item_parent_column	1
262	2023_08_14_110923_delete_vps_os_uuid_column	1
263	2023_08_15_110000_add_order_line_item_meta_data_column	1
264	2023_08_22_152407_change_twofactor_secret_datatype	1
265	2023_08_24_141537_change_mollie_reference_signature_date_nullable	1
266	2023_08_31_105813_mandate_migrated_customer	1
267	2023_09_01_113549_mandate_signature_date_not_nullable	1
268	2023_09_04_161006_create_rtr_response_log_table	1
269	2023_09_05_161006_create_domain_provider_status_table	1
270	2023_09_06_105800_invoice_line_credit_reason	1
271	2023_09_27_161006_domain_provider_history_nullable_domain_id	1
272	2023_09_27_161007_hubspot_events_increase_messages_size	1
273	2023_09_28_100000_make_noted_by_nullable_in_notes	1
274	2023_09_29_100000_move_internal_comments_to_notes	1
275	2023_10_03_171007_microsoft_tenant_id_varchar	1
276	2023_10_09_165619_add_key_columns_cloudstack_environments_table	1
277	2023_10_16_125644_one_time_services_table	1
278	2023_11_08_095431_one_time_services_update	1
279	2023_11_21_105143_nova_action_events_user_uuid	1
280	2023_12_04_105142_user_uuid_type_and_unique	1
281	2023_12_04_105143_notes_replace_user_id_with_uuid	1
282	2023_12_05_115244_orders_replace_user_id_with_uuid	1
283	2023_12_13_114442_update_microsoft365_customer_info_default_synced_at	1
284	2023_12_18_105143_remove_user_id_fk	1
285	2023_12_29_161006_reseller_hosting_subscription_nullable_provider	1
286	2024_01_04_161006_audit_logs_user_uuid	1
287	2024_01_10_161006_audit_logs_remove_user_fk	1
288	2024_01_29_170650_rename_table_hosting_changes_to_subscription_changes	1
289	2024_01_29_173337_drop_server_columns_from_subscription_changes	1
290	2024_01_30_145438_add_products_default_price_id	1
291	2024_01_30_151847_add_product_groups_default_add_period_and_default_contract_period	1
292	2024_02_27_145800_product_price_add_action_period	1
293	2024_03_13_163330_subscriptions_table_add_termination_date_column	1
294	2024_03_29_104810_create_one_offs_table	1
295	2024_04_02_122158_create_dns_record_changes	1
296	2024_04_04_085155_create_dns_deployments_table	1
297	2024_04_04_085227_create_dns_vanity_nameservers_table	1
298	2024_04_04_115030_subscriptions_table_add_cancel_reason_column	1
299	2024_04_04_163330_create_domain_contact_anonymous_handles_table	1
300	2024_04_05_102430_payments_table_add_create_direct_debit_column	1
301	2024_04_08_154439_create_dns_deployment_dns_vanity_nameserver	1
302	2024_04_16_161006_dns_record_changes_remove_user_fk	1
303	2024_04_24_083858_create_product_promotions_table	1
304	2024_04_25_094253_add_customer_data_verified_at_timestap	1
305	2024_04_30_110234_drop_hosting_sanity_table	1
306	2024_05_08_114607_update_dns_customer_template_records_content_length	1
307	2024_05_14_131809_add_dnssec_private_whois_transfer_secret_columns_to_domain_deployment	1
308	2024_05_17_112137_add_one_time_service_id_to_order_line_items	1
309	2024_05_28_103330_create_provider_table	1
310	2024_05_29_131809_remove_migrations	1
311	2024_06_05_103826_remove_migrate_to_ssl_providers	1
312	2024_06_10_170000_remove_order_line_forwarding_url	1
313	2024_06_11_103826_migrate_ssl_providers	1
314	2024_06_19_150000_add_retention_as_credit_reason	1
315	2024_06_20_170000_create_hosting_legacy_forwarding_servers_table	1
316	2024_07_02_103924_add_uses_vanity_nameservers_attribute	1
317	2024_07_10_133924_remove_passport	1
318	2024_07_11_103826_migrate_hosting_providers	1
319	2024_07_15_143528_create_allowed_subscription_product_changes_table	1
320	2024_07_17_092758_add_weight_to_product_coupling	1
321	2024_07_17_154330_create_migrated_dns_templates	1
322	2024_07_17_154337_create_migrated_dns_template_records	1
323	2024_07_18_103528_create_provider_settings	1
324	2024_07_24_103826_migrate_mail_only_providers	1
325	2024_07_26_152747_add_last_result_columns_to_vm_deployments	1
326	2024_07_29_172419_drop_product_changes_table	1
327	2024_07_30_093528_create_label_table	1
328	2024_07_30_103528_alter_subscription_change	1
329	2024_07_30_103528_create_provider_settings_timestamps	1
330	2024_07_30_103528_remove_roles_permissions	1
331	2024_07_30_113528_create_label_subscription_table	1
332	2024_07_31_110711_add_customer_number_sequence	1
333	2024_07_31_162132_remove_product_change_reference_columns_from_product_table	1
334	2024_08_13_103826_migrate_sitebuilder_providers	1
335	2024_08_15_102132_migrate_customer_internal_comments_to_notes_table	1
336	2024_09_16_110032_create_subscription_mutations_table	1
337	2024_09_19_203300_remove_user_table	1
338	2024_09_20_1500_add_merge_on_pdf_with_invoice_id	1
339	2024_09_23_145434_add_wp_installation_id_to_hosting_deployment	1
340	2024_09_25_143725_add_customer_has_direct_debit	1
341	2024_09_25_1500_one_time_services_uuid	1
342	2024_09_25_1600_hubspot_events_one_time_services_id	1
343	2024_10_01_1600_email_history_drop_columns	1
344	2024_10_03_120000_add_abuse_as_credit_reason	1
345	2024_10_07_151022_add-template-id-to-templates-table	1
346	2024_10_07_152259_add_ouput_last_run_to_one_off_scripts	1
347	2024_10_07_1631_create_hosting_product_compositions_table	1
348	2024_10_08_140949_remove_is_forwarding_column_from_hosting_servers	1
349	2024_10_08_144140_add-hubspot-email-data-to-email-history-table	1
350	2024_10_08_151023_change_hubspot_event_subscription_id	1
351	2024_10_21_113923_remove_hosting_is_free_from_server	1
352	2024_10_21_144140_add-last-result-to-email-history	1
353	2024_10_28_111428_add_default_price_column_to_product_prices_table	1
354	2024_10_28_133922_remove_default_product_price_from_products_table	1
355	2024_10_30_082927_create_dns_deployment_dns_nameserver	1
356	2024_11_04_170000_add_customer_is_abuse	1
357	2024_11_08_170000_create_spamexperts_clusters_table	1
358	2024_11_08_170001_add_spamexperts_cluster_id_to_hosting_servers_table	1
359	2024_11_12_170000_email_history_hubspot_id_varchar	1
360	2024_11_13_170000_templates_hubspot_id_varchar	1
361	2024_11_14_170000_hubspot_events_drop_ots_id	1
362	2024_11_18_155444_add_nameserver_type_dns_deployment	1
363	2024_11_18_1629_add_wp_columns_to_hosting_product_compositions	1
364	2024_11_19_110000_voucher_add_voucher_amount_type	1
365	2024_11_19_112416_create_dns_external_nameserver_table	1
366	2024_11_22_100000_create_ssh_keys_table	1
367	2024_11_25_120000_add_columns_to_invoices	1
368	2024_11_27_120000_make_columns_not_nullable_to_invoices	1
369	2024_11_27_180000_create_vps_deployment_ssh_keys_table	1
370	2024_11_28_135614_add_product_id_to_invoices	1
371	2024_12_02_114000_remove_customer_discount_vouchers_view	1
372	2024_12_02_115721_refactor_voucher_claims	1
373	2024_12_09_090000_add_unique_to_ssh_keys_table	1
374	2024_12_09_130000_account_number_renamed_to_ledger_code	1
375	2024_12_09_152000_subscription_uuid_to_id	1
376	2024_12_10_102259_add_billing_and_contract_period_to_vouchers_table	1
377	2024_12_12_165500_invoice_remove_subscription_uuid	1
378	2024_12_13_152709_create_cloudstack_environments_cloudstack_vm_ssh_keys_table	1
379	2024_12_23_142435_drop_uses_vanity_nameservers_attribute	1
380	2025_01_07_140300_invoice_properties_not_nullable	1
381	2025_01_10_170547_update_migration_state_view	1
382	2025_01_23_081924_add_order_admin_fee	1
383	2025_01_27_160428_add_last_action_status_column_to_cloudstack_vm_subscriptions	1
384	2025_01_28_100000_add_is_invoiced_to_order	1
385	2025_01_31_102913_product_slug_unique	1
386	2025_02_10_170547_update_migration_state_view	1
387	2025_02_11_102913_product_price_unique_with_nullable	1
388	2025_02_12_140000_invoice_remove_subscription_uuid	1
389	2025_02_14_130000_invoice_remove_redundant_product_properties	1
390	2025_02_17_093505_create_table_provisioning_requests	1
391	2025_02_17_111025_create_provisioning_results_table	1
392	2025_02_18_164707_create_cloudstack_managerdomain_cloudstack_vm_ssh_keys	1
393	2025_02_25_111025_remove_telescope	1
394	2025_02_26_132610_invoices_table_indexes	1
395	2025_02_28_120000_add_type_to_m365_customerinfo	1
396	2025_03_03_141413_rename_hosting_forwarding_legacy_servers_to_redirects	1
397	2025_03_06_143229_remove_nullable_type_microsoft365_customerinfo	1
398	2025_03_07_103700_remove_domain_blacklist_table	1
399	2025_03_10_093505_create_table_migrated_subscription_steps	1
400	2025_03_11_094000_add_steet_number_addition_table	1
401	2025_03_11_162613_subscriptions_table_indexes	1
402	2025_03_12_132758_remove_cloudstack_environments_cloudstack_vm_ssh_keys_table	1
403	2025_03_17_174200_remove_domain_categories	1
404	2025_03_20_103136_add_custom_name_to_cloudstack_vm_subscriptions	1
405	2025_03_20_103546_subscription_not_nullable_prices	1
406	2025_03_21_113204_add_request_name_to_provisioning_requests	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 406, true);


--
-- PostgreSQL database dump complete
--

