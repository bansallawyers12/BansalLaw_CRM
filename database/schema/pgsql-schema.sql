--
-- PostgreSQL database dump
--

\restrict WLhNTcZejcsHACHN92RXegMrf72SFitmPxU6E2X72OgjhH7RRh19EwP9KXTMieD

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
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
-- Name: account_all_invoice_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_all_invoice_receipts (
    id bigint NOT NULL,
    user_id bigint,
    client_id bigint,
    client_matter_id bigint,
    receipt_id bigint,
    receipt_type smallint,
    trans_date character varying(32),
    entry_date character varying(32),
    trans_no character varying(191),
    gst_included character varying(32),
    payment_type character varying(191),
    description text,
    withdraw_amount numeric(15,2),
    balance_amount numeric(15,2),
    invoice_no character varying(191),
    save_type character varying(32),
    invoice_status smallint DEFAULT '0'::smallint NOT NULL,
    withdraw_amount_before_void numeric(15,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: account_all_invoice_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_all_invoice_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_all_invoice_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_all_invoice_receipts_id_seq OWNED BY public.account_all_invoice_receipts.id;


--
-- Name: account_client_receipts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_client_receipts (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    void_fee_transfer smallint DEFAULT '0'::smallint,
    voided_at timestamp(0) without time zone,
    voided_by bigint,
    pdf_document_id bigint,
    eftpos_surcharge_amount numeric(10,2),
    user_id bigint,
    client_id bigint,
    client_matter_id bigint,
    receipt_id bigint,
    receipt_type smallint,
    trans_date character varying(32),
    entry_date character varying(32),
    invoice_no character varying(191),
    trans_no character varying(191),
    client_fund_ledger_type character varying(191),
    description text,
    deposit_amount numeric(15,2),
    withdraw_amount numeric(15,2),
    balance_amount numeric(15,2),
    payment_method character varying(191),
    uploaded_doc_id bigint,
    validate_receipt smallint DEFAULT '0'::smallint NOT NULL,
    void_invoice smallint DEFAULT '0'::smallint NOT NULL,
    invoice_status smallint DEFAULT '0'::smallint NOT NULL,
    save_type character varying(32),
    hubdoc_sent boolean DEFAULT false NOT NULL,
    hubdoc_sent_at timestamp(0) without time zone,
    extra_amount_receipt character varying(64),
    gst_included character varying(32),
    payment_type character varying(191),
    agent_id bigint,
    voided_or_validated_by bigint,
    partial_paid_amount numeric(15,2),
    withdraw_amount_before_void numeric(15,2),
    trust_voided_at timestamp(0) without time zone,
    trust_voided_by bigint,
    trust_void_reason text,
    trust_reversal_of_entry_id bigint,
    payer_name character varying(255),
    bank_deposit_reference character varying(191),
    banking_date character varying(32),
    payee_name character varying(255),
    cheque_number character varying(32),
    eft_account_name character varying(255),
    eft_bsb character varying(16),
    eft_account_number character varying(64)
);


--
-- Name: account_client_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_client_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_client_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_client_receipts_id_seq OWNED BY public.account_client_receipts.id;


--
-- Name: activities_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activities_logs (
    id bigint NOT NULL,
    client_id bigint,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sms_log_id bigint,
    activity_type character varying(64) DEFAULT 'note'::character varying NOT NULL,
    source character varying(50),
    created_by bigint,
    subject text,
    task_status smallint DEFAULT '0'::smallint NOT NULL,
    pin smallint DEFAULT '0'::smallint NOT NULL,
    use_for character varying(64),
    followup_date timestamp(0) without time zone,
    task_group character varying(128)
);


--
-- Name: COLUMN activities_logs.sms_log_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.activities_logs.sms_log_id IS 'Reference to SMS log if activity is SMS-related';


--
-- Name: COLUMN activities_logs.activity_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.activities_logs.activity_type IS 'Type: note, document, sms, email, etc.';


--
-- Name: COLUMN activities_logs.source; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.activities_logs.source IS 'Origin: client_portal, crm, etc. NULL = legacy/unset.';


--
-- Name: activities_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activities_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activities_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activities_logs_id_seq OWNED BY public.activities_logs.id;


--
-- Name: admins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.admins (
    id bigint NOT NULL,
    role integer,
    first_name character varying(191),
    last_name character varying(191),
    email character varying(191) NOT NULL,
    password character varying(191) NOT NULL,
    country integer,
    state integer,
    city character varying(191),
    address text,
    zip character varying(191),
    status smallint DEFAULT '1'::smallint NOT NULL,
    service_token character varying(191),
    token_generated_at timestamp(0) without time zone,
    visa_expiry_verified_at timestamp(0) without time zone,
    visa_expiry_verified_by integer,
    naati_test character varying(191),
    py_test character varying(191),
    naati_date date,
    py_date date,
    marital_status character varying(191),
    is_deleted timestamp(0) without time zone,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    australian_study boolean DEFAULT false NOT NULL,
    australian_study_date date,
    specialist_education boolean DEFAULT false NOT NULL,
    specialist_education_date date,
    regional_study boolean DEFAULT false NOT NULL,
    regional_study_date date,
    client_counter character varying(5),
    client_id character varying(20),
    archived_by bigint,
    is_company boolean DEFAULT false,
    lead_status character varying(64),
    followup_date timestamp(0) without time zone,
    google_review_reminder_status character varying(32),
    google_review_reminder_snooze_until timestamp(0) without time zone,
    is_archived smallint DEFAULT 0 NOT NULL,
    archived_on timestamp without time zone,
    type character varying(32),
    refer_by character varying(500),
    dob date,
    age character varying(64),
    gender character varying(32),
    dob_verified_date timestamp(0) without time zone,
    dob_verified_by bigint,
    email_type character varying(191),
    contact_type character varying(191),
    country_code character varying(32),
    phone character varying(100),
    user_id bigint,
    tagname text,
    source character varying(120)
);


--
-- Name: COLUMN admins.australian_study; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.australian_study IS 'Has Australian study requirement (2+ years)';


--
-- Name: COLUMN admins.australian_study_date; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.australian_study_date IS 'Australian study completion date';


--
-- Name: COLUMN admins.specialist_education; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.specialist_education IS 'Has specialist education qualification (STEM)';


--
-- Name: COLUMN admins.specialist_education_date; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.specialist_education_date IS 'Specialist education completion date';


--
-- Name: COLUMN admins.regional_study; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.regional_study IS 'Has regional study (studied in regional Australia)';


--
-- Name: COLUMN admins.regional_study_date; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.regional_study_date IS 'Regional study completion date';


--
-- Name: COLUMN admins.archived_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.archived_by IS 'ID of the admin who archived the client';


--
-- Name: COLUMN admins.is_company; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.admins.is_company IS 'Flag to indicate if this is a company lead/client. Company data is stored in companies table.';


--
-- Name: admins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.admins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: admins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.admins_id_seq OWNED BY public.admins.id;


--
-- Name: agent_details; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.agent_details (
    id bigint NOT NULL,
    business_name character varying(255),
    agent_name character varying(255),
    full_name character varying(255),
    email character varying(255),
    marn_number character varying(128),
    legal_practitioner_number character varying(128),
    business_address text,
    business_phone character varying(64),
    business_mobile character varying(64),
    business_email character varying(255),
    agent_type character varying(64),
    related_office character varying(255),
    struture character varying(255),
    tax_number character varying(128),
    contract_expiry_date date,
    is_acrchived smallint,
    status smallint DEFAULT '1'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: agent_details_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.agent_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: agent_details_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.agent_details_id_seq OWNED BY public.agent_details.id;


--
-- Name: cp_doc_checklists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cp_doc_checklists (
    id bigint CONSTRAINT application_document_lists_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    wf_stage character varying(255),
    wf_stage_id bigint
);


--
-- Name: application_document_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.application_document_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: application_document_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.application_document_lists_id_seq OWNED BY public.cp_doc_checklists.id;


--
-- Name: appointment_consultants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.appointment_consultants (
    id bigint NOT NULL,
    name character varying(191) NOT NULL,
    email character varying(191),
    calendar_type character varying(255) NOT NULL,
    location character varying(255) DEFAULT 'melbourne'::character varying NOT NULL,
    specializations json,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT appointment_consultants_calendar_type_check CHECK (((calendar_type)::text = ANY ((ARRAY['paid'::character varying, 'jrp'::character varying, 'education'::character varying, 'tourist'::character varying, 'adelaide'::character varying, 'ajay'::character varying, 'kunal'::character varying])::text[]))),
    CONSTRAINT appointment_consultants_location_check CHECK (((location)::text = ANY ((ARRAY['melbourne'::character varying, 'adelaide'::character varying])::text[])))
);


--
-- Name: COLUMN appointment_consultants.specializations; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_consultants.specializations IS 'Array of noe_ids this consultant handles';


--
-- Name: appointment_consultants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.appointment_consultants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: appointment_consultants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.appointment_consultants_id_seq OWNED BY public.appointment_consultants.id;


--
-- Name: appointment_payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.appointment_payments (
    id bigint NOT NULL,
    appointment_id bigint NOT NULL,
    payment_gateway character varying(255) DEFAULT 'stripe'::character varying NOT NULL,
    transaction_id character varying(191),
    charge_id character varying(191),
    customer_id character varying(191),
    payment_method_id character varying(191),
    amount numeric(10,2) NOT NULL,
    currency character varying(3) DEFAULT 'AUD'::character varying NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    transaction_data json,
    receipt_url character varying(191),
    refund_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    refunded_at timestamp(0) without time zone,
    client_ip character varying(45),
    user_agent text,
    processed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT appointment_payments_payment_gateway_check CHECK (((payment_gateway)::text = ANY ((ARRAY['stripe'::character varying, 'paypal'::character varying, 'manual'::character varying])::text[]))),
    CONSTRAINT appointment_payments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'processing'::character varying, 'succeeded'::character varying, 'failed'::character varying, 'refunded'::character varying, 'partially_refunded'::character varying])::text[])))
);


--
-- Name: COLUMN appointment_payments.appointment_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.appointment_id IS 'FK to booking_appointments.id';


--
-- Name: COLUMN appointment_payments.transaction_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.transaction_id IS 'Stripe PaymentIntent ID (pi_xxx)';


--
-- Name: COLUMN appointment_payments.charge_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.charge_id IS 'Stripe Charge ID (ch_xxx)';


--
-- Name: COLUMN appointment_payments.customer_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.customer_id IS 'Stripe Customer ID (cus_xxx)';


--
-- Name: COLUMN appointment_payments.payment_method_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.payment_method_id IS 'Stripe Payment Method ID (pm_xxx)';


--
-- Name: COLUMN appointment_payments.amount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.amount IS 'Payment amount';


--
-- Name: COLUMN appointment_payments.currency; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.currency IS 'Currency code';


--
-- Name: COLUMN appointment_payments.error_message; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.error_message IS 'Error message if payment failed';


--
-- Name: COLUMN appointment_payments.transaction_data; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.transaction_data IS 'Full Stripe response JSON';


--
-- Name: COLUMN appointment_payments.receipt_url; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.receipt_url IS 'Stripe receipt URL';


--
-- Name: COLUMN appointment_payments.refund_amount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.refund_amount IS 'Total refunded amount';


--
-- Name: COLUMN appointment_payments.client_ip; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.client_ip IS 'Client IP address';


--
-- Name: COLUMN appointment_payments.user_agent; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.user_agent IS 'Client user agent';


--
-- Name: COLUMN appointment_payments.processed_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_payments.processed_at IS 'When payment was processed';


--
-- Name: appointment_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.appointment_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: appointment_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.appointment_payments_id_seq OWNED BY public.appointment_payments.id;


--
-- Name: appointment_sync_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.appointment_sync_logs (
    id bigint NOT NULL,
    sync_type character varying(255) DEFAULT 'polling'::character varying NOT NULL,
    started_at timestamp(0) without time zone NOT NULL,
    completed_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'running'::character varying NOT NULL,
    appointments_fetched integer DEFAULT 0 NOT NULL,
    appointments_new integer DEFAULT 0 NOT NULL,
    appointments_updated integer DEFAULT 0 NOT NULL,
    appointments_skipped integer DEFAULT 0 NOT NULL,
    appointments_failed integer DEFAULT 0 NOT NULL,
    error_message text,
    sync_details json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT appointment_sync_logs_status_check CHECK (((status)::text = ANY ((ARRAY['running'::character varying, 'success'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT appointment_sync_logs_sync_type_check CHECK (((sync_type)::text = ANY ((ARRAY['polling'::character varying, 'manual'::character varying, 'backfill'::character varying])::text[])))
);


--
-- Name: COLUMN appointment_sync_logs.sync_details; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.appointment_sync_logs.sync_details IS 'API response, errors, etc.';


--
-- Name: appointment_sync_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.appointment_sync_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: appointment_sync_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.appointment_sync_logs_id_seq OWNED BY public.appointment_sync_logs.id;


--
-- Name: booking_appointments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.booking_appointments (
    id bigint NOT NULL,
    bansal_appointment_id bigint NOT NULL,
    order_hash character varying(191),
    client_id integer,
    consultant_id bigint,
    assigned_by_admin_id bigint,
    client_name character varying(191) NOT NULL,
    client_email character varying(191) NOT NULL,
    client_phone character varying(50),
    client_timezone character varying(50) DEFAULT 'Australia/Melbourne'::character varying NOT NULL,
    appointment_datetime timestamp(0) without time zone NOT NULL,
    timeslot_full character varying(50),
    duration_minutes integer DEFAULT 15 NOT NULL,
    location character varying(255) NOT NULL,
    inperson_address smallint,
    meeting_type character varying(255) DEFAULT 'in_person'::character varying NOT NULL,
    preferred_language character varying(50) DEFAULT 'English'::character varying NOT NULL,
    service_id smallint,
    noe_id smallint,
    enquiry_type character varying(100),
    service_type character varying(100),
    enquiry_details text,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    confirmed_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    cancellation_reason text,
    is_paid boolean DEFAULT false NOT NULL,
    amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    final_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    promo_code character varying(50),
    payment_status character varying(255),
    payment_method character varying(50),
    paid_at timestamp(0) without time zone,
    admin_notes text,
    confirmation_email_sent boolean DEFAULT false NOT NULL,
    confirmation_email_sent_at timestamp(0) without time zone,
    reminder_sms_sent boolean DEFAULT false NOT NULL,
    reminder_sms_sent_at timestamp(0) without time zone,
    synced_from_bansal_at timestamp(0) without time zone,
    last_synced_at timestamp(0) without time zone,
    sync_status character varying(255) DEFAULT 'new'::character varying NOT NULL,
    sync_error text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint,
    website_status_code smallint,
    CONSTRAINT booking_appointments_location_check CHECK (((location)::text = ANY ((ARRAY['melbourne'::character varying, 'adelaide'::character varying])::text[]))),
    CONSTRAINT booking_appointments_meeting_type_check CHECK (((meeting_type)::text = ANY ((ARRAY['in_person'::character varying, 'phone'::character varying, 'video'::character varying])::text[]))),
    CONSTRAINT booking_appointments_payment_status_check CHECK (((payment_status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'failed'::character varying, 'refunded'::character varying])::text[]))),
    CONSTRAINT booking_appointments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'paid'::character varying, 'confirmed'::character varying, 'completed'::character varying, 'cancelled'::character varying, 'no_show'::character varying, 'rescheduled'::character varying])::text[]))),
    CONSTRAINT booking_appointments_sync_status_check CHECK (((sync_status)::text = ANY ((ARRAY['new'::character varying, 'synced'::character varying, 'error'::character varying])::text[])))
);


--
-- Name: COLUMN booking_appointments.bansal_appointment_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.bansal_appointment_id IS 'ID from Bansal website';


--
-- Name: COLUMN booking_appointments.order_hash; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.order_hash IS 'Payment order hash from Bansal';


--
-- Name: COLUMN booking_appointments.client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.client_id IS 'FK to admins.id (clients/leads)';


--
-- Name: COLUMN booking_appointments.consultant_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.consultant_id IS 'FK to appointment_consultants.id';


--
-- Name: COLUMN booking_appointments.timeslot_full; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.timeslot_full IS 'e.g., "9:00 AM - 9:15 AM"';


--
-- Name: COLUMN booking_appointments.inperson_address; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.inperson_address IS 'Legacy: 1=Adelaide, 2=Melbourne';


--
-- Name: COLUMN booking_appointments.service_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.service_id IS 'Legacy: 1=Paid, 2=Free';


--
-- Name: COLUMN booking_appointments.noe_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.noe_id IS 'Legacy: Nature of Enquiry ID';


--
-- Name: COLUMN booking_appointments.enquiry_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.enquiry_type IS 'tr, tourist, education, etc.';


--
-- Name: COLUMN booking_appointments.service_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.service_type IS 'Display name';


--
-- Name: COLUMN booking_appointments.website_status_code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.booking_appointments.website_status_code IS 'Public booking UI status 0–11 (optional)';


--
-- Name: booking_appointments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.booking_appointments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: booking_appointments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.booking_appointments_id_seq OWNED BY public.booking_appointments.id;


--
-- Name: branches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    office_name character varying(255) DEFAULT ''::character varying NOT NULL,
    address character varying(255),
    city character varying(255),
    state character varying(255),
    zip character varying(32),
    country character varying(255),
    email character varying(255),
    phone character varying(64),
    mobile character varying(64),
    contact_person character varying(255),
    choose_admin character varying(255)
);


--
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(191) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(191) NOT NULL,
    owner character varying(191) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: checkin_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.checkin_history (
    id bigint NOT NULL,
    subject character varying(191),
    created_by bigint,
    checkin_id bigint NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: checkin_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.checkin_history_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: checkin_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.checkin_history_id_seq OWNED BY public.checkin_history.id;


--
-- Name: checkin_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.checkin_logs (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    walk_in_phone character varying(32),
    walk_in_email character varying(255),
    client_id bigint,
    user_id bigint,
    visit_purpose text,
    office bigint,
    contact_type character varying(32),
    status smallint DEFAULT '0'::smallint NOT NULL,
    date date,
    sesion_start timestamp(0) without time zone,
    sesion_end timestamp(0) without time zone,
    wait_time character varying(64),
    attend_time character varying(64),
    wait_type smallint
);


--
-- Name: checkin_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.checkin_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: checkin_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.checkin_logs_id_seq OWNED BY public.checkin_logs.id;


--
-- Name: client_access_grants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_access_grants (
    id bigint NOT NULL,
    staff_id bigint NOT NULL,
    admin_id bigint NOT NULL,
    record_type character varying(10) NOT NULL,
    grant_type character varying(20) NOT NULL,
    access_type character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    quick_reason_code character varying(50),
    requester_note text,
    office_id bigint,
    office_label_snapshot character varying(255),
    team_id bigint,
    team_label_snapshot character varying(255),
    requested_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    approved_at timestamp(0) with time zone,
    approved_by_staff_id bigint,
    starts_at timestamp(0) with time zone,
    ends_at timestamp(0) with time zone,
    revoked_at timestamp(0) with time zone,
    revoke_reason text,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: client_access_grants_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_access_grants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_access_grants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_access_grants_id_seq OWNED BY public.client_access_grants.id;


--
-- Name: client_addresses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_addresses (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    suburb character varying(100),
    country character varying(100) DEFAULT 'Australia'::character varying NOT NULL,
    zip character varying(20),
    client_id bigint,
    admin_id bigint,
    address text,
    state character varying(100),
    regional_code character varying(50),
    start_date date,
    end_date date,
    is_current boolean DEFAULT false NOT NULL
);


--
-- Name: client_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_addresses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_addresses_id_seq OWNED BY public.client_addresses.id;


--
-- Name: client_art_references; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_art_references (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    client_matter_id bigint NOT NULL,
    submission_last_date date,
    status_of_file character varying(50) DEFAULT 'submission_pending'::character varying NOT NULL,
    hearing_time character varying(191),
    member_name character varying(191),
    outcome character varying(191),
    comments text,
    created_by bigint,
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_pinned boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN client_art_references.status_of_file; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_art_references.status_of_file IS 'submission_pending, submission_done, hearing_invitation_sent, waiting_for_hearing, hearing, decided, withdrawn';


--
-- Name: COLUMN client_art_references.hearing_time; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_art_references.hearing_time IS 'e.g. 10:30 am (NSW time)';


--
-- Name: COLUMN client_art_references.member_name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_art_references.member_name IS 'Tribunal member';


--
-- Name: client_art_references_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_art_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_art_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_art_references_id_seq OWNED BY public.client_art_references.id;


--
-- Name: client_characters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_characters (
    id bigint NOT NULL,
    client_id bigint,
    admin_id bigint,
    type_of_character smallint,
    character_detail text,
    character_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_characters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_characters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_characters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_characters_id_seq OWNED BY public.client_characters.id;


--
-- Name: client_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_contacts (
    id bigint NOT NULL,
    admin_id bigint,
    client_id bigint,
    contact_type character varying(191),
    country_code character varying(16),
    phone character varying(64),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_verified boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    verified_by integer
);


--
-- Name: client_contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_contacts_id_seq OWNED BY public.client_contacts.id;


--
-- Name: client_court_hearings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_court_hearings (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    client_matter_id bigint,
    court_name character varying(255),
    case_number character varying(100),
    judge_name character varying(150),
    hearing_date date NOT NULL,
    hearing_time time(0) without time zone,
    hearing_type character varying(100),
    notes text,
    status character varying(50) DEFAULT 'Scheduled'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_court_hearings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_court_hearings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_court_hearings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_court_hearings_id_seq OWNED BY public.client_court_hearings.id;


--
-- Name: client_emails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_emails (
    id bigint NOT NULL,
    admin_id bigint,
    client_id bigint,
    email_type character varying(191),
    email character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_verified boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    verified_by integer,
    verification_token character varying(255),
    token_expires_at timestamp(0) without time zone,
    verification_sent_at timestamp(0) without time zone
);


--
-- Name: client_emails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_emails_id_seq OWNED BY public.client_emails.id;


--
-- Name: client_experiences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_experiences (
    id bigint NOT NULL,
    client_id bigint,
    job_country character varying(191),
    job_type character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fte_multiplier numeric(3,2) DEFAULT '1'::numeric NOT NULL,
    admin_id bigint,
    job_title character varying(500),
    job_code character varying(100),
    job_start_date date,
    job_finish_date date,
    relevant_experience boolean DEFAULT false NOT NULL,
    job_emp_name character varying(500),
    job_state character varying(100)
);


--
-- Name: COLUMN client_experiences.fte_multiplier; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_experiences.fte_multiplier IS 'Full-time equivalent multiplier (1.00 = full-time, 0.50 = half-time, etc.)';


--
-- Name: client_experiences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_experiences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_experiences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_experiences_id_seq OWNED BY public.client_experiences.id;


--
-- Name: client_legal_forms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_legal_forms (
    id bigint NOT NULL,
    client_id bigint NOT NULL,
    client_matter_id bigint,
    created_by bigint NOT NULL,
    form_type character varying(255) NOT NULL,
    matter_reference character varying(191),
    firm_name character varying(191) DEFAULT 'Bansal Lawyers'::character varying NOT NULL,
    firm_contact character varying(191),
    firm_address text DEFAULT 'Level 8, 278 Collins Street, Melbourne VIC 3000'::text NOT NULL,
    firm_phone character varying(191) DEFAULT '0422 905 860'::character varying NOT NULL,
    firm_mobile character varying(191),
    firm_email character varying(191) DEFAULT 'info@bansallawyers.com.au'::character varying NOT NULL,
    firm_state character varying(191) DEFAULT 'VIC'::character varying NOT NULL,
    firm_postcode character varying(191) DEFAULT '3000'::character varying NOT NULL,
    person_responsible character varying(191),
    person_responsible_email character varying(191),
    scope_of_work text,
    estimated_legal_fees numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    estimated_disbursements numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    estimated_barrister_fees numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    gst_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    estimated_total numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    fee_type character varying(191),
    fixed_fee_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    cost_estimate_breakdown text,
    variables_affecting_costs text,
    retainer_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    trust_account_name character varying(191) DEFAULT 'BANSAL Lawyers'::character varying NOT NULL,
    trust_account_institution character varying(191) DEFAULT 'NAB'::character varying NOT NULL,
    trust_account_bsb character varying(191) DEFAULT '083419'::character varying NOT NULL,
    trust_account_number character varying(191) DEFAULT '787266100'::character varying NOT NULL,
    payment_reference character varying(191),
    authority_scope text,
    pdf_path character varying(191),
    form_date date,
    signed_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT client_legal_forms_form_type_check CHECK (((form_type)::text = ANY ((ARRAY['short_costs_disclosure'::character varying, 'cost_agreement'::character varying, 'authority_to_act'::character varying])::text[])))
);


--
-- Name: client_legal_forms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_legal_forms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_legal_forms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_legal_forms_id_seq OWNED BY public.client_legal_forms.id;


--
-- Name: client_matter_opposing_parties; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_matter_opposing_parties (
    id bigint NOT NULL,
    client_matter_id bigint NOT NULL,
    name character varying(500) NOT NULL,
    party_role character varying(255),
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_matter_opposing_parties_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_matter_opposing_parties_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_matter_opposing_parties_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_matter_opposing_parties_id_seq OWNED BY public.client_matter_opposing_parties.id;


--
-- Name: client_matter_payment_forms_verifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_matter_payment_forms_verifications (
    id bigint NOT NULL,
    client_matter_id bigint CONSTRAINT client_matter_payment_forms_verificat_client_matter_id_not_null NOT NULL,
    verified_by bigint NOT NULL,
    verified_at timestamp(0) without time zone NOT NULL,
    note text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN client_matter_payment_forms_verifications.verified_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matter_payment_forms_verifications.verified_by IS 'Migration Agent (staff id) who verified';


--
-- Name: COLUMN client_matter_payment_forms_verifications.note; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matter_payment_forms_verifications.note IS 'Optional text from Migration Agent';


--
-- Name: client_matter_payment_forms_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_matter_payment_forms_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_matter_payment_forms_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_matter_payment_forms_verifications_id_seq OWNED BY public.client_matter_payment_forms_verifications.id;


--
-- Name: client_matter_references; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_matter_references (
    id bigint NOT NULL,
    type character varying(50) NOT NULL,
    client_id bigint NOT NULL,
    client_matter_id bigint NOT NULL,
    current_status text,
    payment_display_note character varying(100),
    institute_override character varying(255),
    visa_category_override character varying(50),
    comments text,
    checklist_sent_at date,
    is_pinned boolean DEFAULT false NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_matter_references_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_matter_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_matter_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_matter_references_id_seq OWNED BY public.client_matter_references.id;


--
-- Name: client_matter_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_matter_tasks (
    id bigint NOT NULL,
    client_matter_id bigint NOT NULL,
    client_id bigint NOT NULL,
    title character varying(500) NOT NULL,
    is_done boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_matter_tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_matter_tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_matter_tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_matter_tasks_id_seq OWNED BY public.client_matter_tasks.id;


--
-- Name: client_matters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_matters (
    id bigint NOT NULL,
    client_id bigint,
    matter_status character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    office_id integer,
    tr_checklist_status character varying(32),
    visitor_checklist_status character varying(32),
    student_checklist_status character varying(32),
    pr_checklist_status character varying(32),
    employer_sponsored_checklist_status character varying(32),
    deadline date,
    decision_outcome character varying(50),
    decision_note text,
    workflow_id bigint,
    partner_checklist_status character varying(32),
    parents_checklist_status character varying(32),
    sel_matter_id bigint,
    workflow_stage_id bigint,
    sel_person_responsible bigint,
    sel_person_assisting bigint,
    client_unique_matter_no character varying(191),
    user_id bigint,
    case_detail text,
    date_of_incidence date,
    incidence_type character varying(255),
    sel_legal_practitioner bigint,
    our_party_role character varying(64),
    trust_last_statement_sent_at timestamp(0) without time zone
);


--
-- Name: COLUMN client_matters.office_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.office_id IS 'Manually assigned handling office';


--
-- Name: COLUMN client_matters.tr_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.tr_checklist_status IS 'TR sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.visitor_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.visitor_checklist_status IS 'Visitor sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.student_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.student_checklist_status IS 'Student sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.pr_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.pr_checklist_status IS 'PR sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.employer_sponsored_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.employer_sponsored_checklist_status IS 'Employer Sponsored sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.deadline; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.deadline IS 'Optional matter deadline; null when not set';


--
-- Name: COLUMN client_matters.partner_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.partner_checklist_status IS 'Partner Visa sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: COLUMN client_matters.parents_checklist_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_matters.parents_checklist_status IS 'Parents Visa sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- Name: client_matters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_matters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_matters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_matters_id_seq OWNED BY public.client_matters.id;


--
-- Name: client_occupations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_occupations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    client_id bigint,
    admin_id bigint,
    skill_assessment character varying(255),
    nomi_occupation character varying(500),
    occupation_code character varying(100),
    list character varying(100),
    visa_subclass character varying(100),
    dates date,
    expiry_dates date,
    relevant_occupation boolean DEFAULT false NOT NULL,
    occ_reference_no character varying(191)
);


--
-- Name: client_occupations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_occupations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_occupations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_occupations_id_seq OWNED BY public.client_occupations.id;


--
-- Name: client_passport_informations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_passport_informations (
    id bigint NOT NULL,
    client_id bigint,
    admin_id bigint,
    passport character varying(191),
    passport_number character varying(191),
    passport_country bigint,
    passport_issue_date date,
    passport_expiry_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_passport_informations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_passport_informations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_passport_informations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_passport_informations_id_seq OWNED BY public.client_passport_informations.id;


--
-- Name: client_qualifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_qualifications (
    id bigint NOT NULL,
    client_id bigint,
    country character varying(191),
    relevant_qualification boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    admin_id bigint,
    level character varying(191),
    name character varying(500),
    qual_college_name character varying(500),
    qual_campus character varying(255),
    qual_state character varying(100),
    start_date date,
    finish_date date
);


--
-- Name: client_qualifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_qualifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_qualifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_qualifications_id_seq OWNED BY public.client_qualifications.id;


--
-- Name: client_relationships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_relationships (
    id bigint NOT NULL,
    admin_id bigint,
    client_id bigint,
    related_client_id bigint,
    details text,
    relationship_type character varying(191),
    company_type character varying(191),
    email character varying(255),
    first_name character varying(255),
    last_name character varying(255),
    phone character varying(64),
    gender character varying(64),
    dob date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_relationships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_relationships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_relationships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_relationships_id_seq OWNED BY public.client_relationships.id;


--
-- Name: client_spouse_details; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_spouse_details (
    id bigint NOT NULL,
    client_id bigint,
    spouse_assessment_date date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_citizen boolean DEFAULT false NOT NULL,
    has_pr boolean DEFAULT false NOT NULL,
    dob date,
    related_client_id bigint
);


--
-- Name: COLUMN client_spouse_details.is_citizen; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_spouse_details.is_citizen IS 'Partner is Australian citizen';


--
-- Name: COLUMN client_spouse_details.has_pr; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_spouse_details.has_pr IS 'Partner has Australian Permanent Residency (PR)';


--
-- Name: COLUMN client_spouse_details.dob; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_spouse_details.dob IS 'Partner date of birth for points calculation';


--
-- Name: COLUMN client_spouse_details.related_client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_spouse_details.related_client_id IS 'Reference to the partner client ID for EOI calculation';


--
-- Name: client_spouse_details_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_spouse_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_spouse_details_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_spouse_details_id_seq OWNED BY public.client_spouse_details.id;


--
-- Name: client_testscore; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_testscore (
    id bigint NOT NULL,
    overall_score character varying(32),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    proficiency_level character varying(191),
    proficiency_points integer,
    client_id bigint,
    admin_id bigint,
    test_type character varying(50),
    listening character varying(32),
    reading character varying(32),
    writing character varying(32),
    speaking character varying(32),
    test_date date,
    relevant_test boolean DEFAULT false NOT NULL,
    test_reference_no character varying(191)
);


--
-- Name: COLUMN client_testscore.proficiency_level; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_testscore.proficiency_level IS 'Calculated English proficiency level (e.g., Competent English, Proficient English, Superior English)';


--
-- Name: COLUMN client_testscore.proficiency_points; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_testscore.proficiency_points IS 'Points awarded for this proficiency level (0, 10, or 20)';


--
-- Name: client_testscore_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_testscore_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_testscore_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_testscore_id_seq OWNED BY public.client_testscore.id;


--
-- Name: client_travel_informations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_travel_informations (
    id bigint NOT NULL,
    client_id bigint,
    admin_id bigint,
    travel_country_visited character varying(255),
    travel_arrival_date date,
    travel_departure_date date,
    travel_purpose character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: client_travel_informations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_travel_informations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_travel_informations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_travel_informations_id_seq OWNED BY public.client_travel_informations.id;


--
-- Name: client_visa_countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.client_visa_countries (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    client_id bigint,
    admin_id bigint,
    visa_type bigint,
    visa_description text,
    visa_expiry_date date,
    visa_grant_date date
);


--
-- Name: COLUMN client_visa_countries.visa_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.client_visa_countries.visa_type IS 'matters.id';


--
-- Name: client_visa_countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.client_visa_countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: client_visa_countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.client_visa_countries_id_seq OWNED BY public.client_visa_countries.id;


--
-- Name: companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.companies (
    id bigint NOT NULL,
    admin_id bigint NOT NULL,
    company_name character varying(255) NOT NULL,
    trading_name character varying(255),
    "ABN_number" character varying(20),
    "ACN" character varying(20),
    company_type character varying(50),
    company_website character varying(255),
    contact_person_id bigint,
    contact_person_position character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    has_trading_name boolean DEFAULT false NOT NULL,
    trust_name character varying(255),
    trust_abn character varying(64),
    trustee_name character varying(255),
    trustee_details text,
    sponsorship_type character varying(50),
    sponsorship_status character varying(50),
    sponsorship_start_date date,
    sponsorship_end_date date,
    trn character varying(50),
    regional_sponsorship boolean,
    adverse_information boolean,
    previous_sponsorship_notes text,
    annual_turnover numeric(15,2),
    wages_expenditure numeric(15,2),
    workforce_australian_citizens integer,
    workforce_permanent_residents integer,
    workforce_temp_visa_holders integer,
    workforce_total integer,
    business_operating_since date,
    main_business_activity character varying(255),
    lmt_required boolean,
    lmt_start_date date,
    lmt_end_date date,
    lmt_notes text,
    training_position_title character varying(255),
    trainer_name character varying(255),
    workforce_foreign_494 integer,
    workforce_foreign_other_temp_activity integer,
    workforce_foreign_overseas_students integer,
    workforce_foreign_working_holiday integer,
    workforce_foreign_other integer
);


--
-- Name: COLUMN companies.admin_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.admin_id IS 'Reference to admins.id - one-to-one relationship with company lead/client';


--
-- Name: COLUMN companies.company_name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.company_name IS 'Company name';


--
-- Name: COLUMN companies.trading_name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.trading_name IS 'Trading name if different from company name';


--
-- Name: COLUMN companies."ABN_number"; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies."ABN_number" IS 'Australian Business Number (11 digits)';


--
-- Name: COLUMN companies."ACN"; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies."ACN" IS 'Australian Company Number (9 digits)';


--
-- Name: COLUMN companies.company_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.company_type IS 'Business type: Sole Trader, Partnership, Proprietary Company, etc.';


--
-- Name: COLUMN companies.company_website; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.company_website IS 'Company website URL';


--
-- Name: COLUMN companies.contact_person_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.contact_person_id IS 'Reference to admins.id of the primary contact person';


--
-- Name: COLUMN companies.contact_person_position; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.contact_person_position IS 'Position/Title of primary contact person (e.g., HR Manager, Director)';


--
-- Name: COLUMN companies.trn; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.companies.trn IS 'Training Reference Number';


--
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- Name: company_directors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_directors (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    director_name character varying(255),
    director_dob date,
    director_role character varying(100),
    is_primary boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    director_client_id bigint
);


--
-- Name: COLUMN company_directors.director_client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.company_directors.director_client_id IS 'FK to admins.id when director is existing client/lead';


--
-- Name: company_directors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_directors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_directors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_directors_id_seq OWNED BY public.company_directors.id;


--
-- Name: company_nominations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_nominations (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    position_title character varying(255),
    anzsco_code character varying(10),
    position_description text,
    salary numeric(12,2),
    duration character varying(100),
    nominated_client_id bigint,
    nominated_person_name character varying(255),
    trn character varying(50),
    status character varying(50),
    nomination_date date,
    expiry_date date,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN company_nominations.nominated_client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.company_nominations.nominated_client_id IS 'FK to admins.id when person is client/lead';


--
-- Name: COLUMN company_nominations.nominated_person_name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.company_nominations.nominated_person_name IS 'Name when person not in system';


--
-- Name: company_nominations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_nominations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_nominations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_nominations_id_seq OWNED BY public.company_nominations.id;


--
-- Name: company_sponsorships; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_sponsorships (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    sponsorship_type character varying(50),
    sponsorship_status character varying(50),
    sponsorship_start_date date,
    sponsorship_end_date date,
    trn character varying(50),
    regional_sponsorship boolean DEFAULT false NOT NULL,
    adverse_information boolean DEFAULT false NOT NULL,
    previous_sponsorship_notes text,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_sponsorships_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_sponsorships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_sponsorships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_sponsorships_id_seq OWNED BY public.company_sponsorships.id;


--
-- Name: company_trading_names; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.company_trading_names (
    id bigint NOT NULL,
    company_id bigint NOT NULL,
    trading_name character varying(255) NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: company_trading_names_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.company_trading_names_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: company_trading_names_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.company_trading_names_id_seq OWNED BY public.company_trading_names.id;


--
-- Name: cost_assignment_forms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cost_assignment_forms (
    id bigint NOT NULL,
    client_id bigint,
    client_matter_id bigint,
    agent_id bigint,
    "Dept_Additional_Applicant_Charge_Under_18_after_person_surcharg" numeric(15,2),
    "Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharg" numeric(15,2),
    "Block_1_Ex_Tax" numeric(15,2),
    "Block_2_Ex_Tax" numeric(15,2),
    "Block_3_Ex_Tax" numeric(15,2),
    additional_fee_1 numeric(15,2),
    "TotalBLOCKFEE" numeric(15,2),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    "TotalDisbursements" numeric(15,2) DEFAULT '0'::numeric
);


--
-- Name: cost_assignment_forms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cost_assignment_forms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cost_assignment_forms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cost_assignment_forms_id_seq OWNED BY public.cost_assignment_forms.id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    id bigint NOT NULL,
    sortname character varying(8) NOT NULL,
    name character varying(255) NOT NULL,
    phonecode character varying(32),
    status smallint DEFAULT '1'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_id_seq OWNED BY public.countries.id;


--
-- Name: device_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.device_tokens (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    device_token character varying(500) NOT NULL,
    device_name character varying(191),
    device_type character varying(191),
    app_version character varying(191),
    os_version character varying(191),
    is_active boolean DEFAULT true NOT NULL,
    last_used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: device_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.device_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: device_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.device_tokens_id_seq OWNED BY public.device_tokens.id;


--
-- Name: disbursement_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.disbursement_lines (
    id bigint NOT NULL,
    cost_assignment_form_id bigint NOT NULL,
    nature character varying(64) NOT NULL,
    description character varying(191),
    amount numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: disbursement_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.disbursement_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: disbursement_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.disbursement_lines_id_seq OWNED BY public.disbursement_lines.id;


--
-- Name: document_checklists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_checklists (
    id bigint CONSTRAINT portal_document_checklists_id_not_null NOT NULL,
    name character varying(255) CONSTRAINT portal_document_checklists_name_not_null NOT NULL,
    doc_type smallint DEFAULT '1'::smallint CONSTRAINT portal_document_checklists_doc_type_not_null NOT NULL,
    status smallint DEFAULT '1'::smallint CONSTRAINT portal_document_checklists_status_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: document_notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_notes (
    id bigint NOT NULL,
    document_id integer NOT NULL,
    created_by integer NOT NULL,
    action_type character varying(50) NOT NULL,
    note text,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN document_notes.created_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.document_notes.created_by IS 'Admin who performed the action';


--
-- Name: COLUMN document_notes.action_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.document_notes.action_type IS 'associated, detached, status_changed, etc.';


--
-- Name: COLUMN document_notes.note; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.document_notes.note IS 'User-provided note or system-generated description';


--
-- Name: COLUMN document_notes.metadata; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.document_notes.metadata IS 'Additional data: entity type/id, old values, etc.';


--
-- Name: document_notes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.document_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: document_notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.document_notes_id_seq OWNED BY public.document_notes.id;


--
-- Name: documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.documents (
    id bigint NOT NULL,
    status character varying(32),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    created_by integer,
    office_id integer,
    cp_list_id bigint,
    cp_rejection_reason text,
    cp_doc_status smallint,
    lead_id bigint,
    client_id bigint,
    user_id bigint,
    client_matter_id character varying(64),
    type character varying(64),
    doc_type character varying(64),
    folder_name character varying(191),
    mail_type character varying(64),
    checklist character varying(500),
    not_used_doc smallint,
    file_name character varying(500),
    filetype character varying(64),
    myfile text,
    myfile_key text,
    file_size character varying(64),
    signature_doc_link text,
    signed_doc_link text
);


--
-- Name: COLUMN documents.office_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.documents.office_id IS 'Office for ad-hoc documents (without matter)';


--
-- Name: COLUMN documents.cp_list_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.documents.cp_list_id IS 'FK to cp_doc_checklist.id';


--
-- Name: COLUMN documents.cp_doc_status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.documents.cp_doc_status IS '0=InProgress, 1=Approved, 2=Rejected';


--
-- Name: documents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.documents_id_seq OWNED BY public.documents.id;


--
-- Name: email_label_email_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_label_email_log (
    id bigint CONSTRAINT email_label_mail_report_id_not_null NOT NULL,
    email_log_id bigint CONSTRAINT email_label_mail_report_mail_report_id_not_null NOT NULL,
    email_label_id bigint CONSTRAINT email_label_mail_report_email_label_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_label_mail_report_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_label_mail_report_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_label_mail_report_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_label_mail_report_id_seq OWNED BY public.email_label_email_log.id;


--
-- Name: email_labels; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_labels (
    id bigint NOT NULL,
    user_id bigint,
    name character varying(191) NOT NULL,
    color character varying(191) DEFAULT '#3B82F6'::character varying NOT NULL,
    type character varying(191) DEFAULT 'custom'::character varying NOT NULL,
    icon character varying(191),
    description character varying(191),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_labels_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_labels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_labels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_labels_id_seq OWNED BY public.email_labels.id;


--
-- Name: email_log_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_log_attachments (
    id bigint CONSTRAINT mail_report_attachments_id_not_null NOT NULL,
    email_log_id integer CONSTRAINT mail_report_attachments_mail_report_id_not_null NOT NULL,
    filename character varying(191) CONSTRAINT mail_report_attachments_filename_not_null NOT NULL,
    display_name character varying(191),
    content_type character varying(191),
    file_path character varying(500),
    s3_key character varying(500),
    file_size bigint DEFAULT '0'::bigint CONSTRAINT mail_report_attachments_file_size_not_null NOT NULL,
    content_id character varying(191),
    is_inline boolean DEFAULT false CONSTRAINT mail_report_attachments_is_inline_not_null NOT NULL,
    description character varying(191),
    headers json,
    extension character varying(10),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_logs (
    id bigint CONSTRAINT mail_reports_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    python_analysis json,
    python_rendering json,
    sentiment character varying(255) DEFAULT 'neutral'::character varying CONSTRAINT mail_reports_sentiment_not_null NOT NULL,
    language character varying(191),
    enhanced_html text,
    rendered_html text,
    text_preview text,
    security_issues json,
    thread_info json,
    message_id character varying(191),
    thread_id character varying(191),
    received_date timestamp(0) without time zone,
    processed_at timestamp(0) without time zone,
    last_accessed_at timestamp(0) without time zone,
    file_hash character varying(191),
    user_id bigint,
    from_mail character varying(512),
    to_mail text,
    cc text,
    template_id bigint,
    reciept_id bigint,
    subject character varying(512),
    type character varying(64),
    message text,
    attachments text,
    mail_type character varying(64),
    client_id bigint,
    client_matter_id bigint,
    conversion_type character varying(64),
    mail_body_type character varying(32),
    fetch_mail_sent_time timestamp(0) without time zone,
    uploaded_doc_id bigint,
    mail_is_read boolean DEFAULT false NOT NULL,
    CONSTRAINT mail_reports_sentiment_check CHECK (((sentiment)::text = ANY ((ARRAY['positive'::character varying, 'neutral'::character varying, 'negative'::character varying])::text[])))
);


--
-- Name: email_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_templates (
    id bigint NOT NULL,
    type character varying(50) NOT NULL,
    matter_id bigint,
    name character varying(191) NOT NULL,
    subject character varying(191),
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    alias character varying(100)
);


--
-- Name: email_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_templates_id_seq OWNED BY public.email_templates.id;


--
-- Name: email_verifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.email_verifications (
    id bigint NOT NULL,
    client_email_id integer NOT NULL,
    client_id integer NOT NULL,
    email character varying(255) NOT NULL,
    verification_token character varying(255) NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    verified_by integer,
    token_sent_at timestamp(0) without time zone,
    token_expires_at timestamp(0) without time zone,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: email_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.email_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: email_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.email_verifications_id_seq OWNED BY public.email_verifications.id;


--
-- Name: emails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.emails (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    email character varying(255),
    display_name character varying(255),
    status boolean DEFAULT true NOT NULL,
    email_signature text,
    user_id text
);


--
-- Name: emails_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.emails_id_seq OWNED BY public.emails.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(191) NOT NULL,
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
-- Name: front_desk_check_ins; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.front_desk_check_ins (
    id bigint NOT NULL,
    admin_id bigint NOT NULL,
    phone_normalized character varying(30) NOT NULL,
    email character varying(255),
    client_id bigint,
    lead_id bigint,
    appointment_id bigint,
    claimed_appointment boolean DEFAULT false NOT NULL,
    visit_reason character varying(100),
    visit_notes text,
    notified_staff_id bigint,
    notified_at timestamp(0) without time zone,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN front_desk_check_ins.admin_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.front_desk_check_ins.admin_id IS 'Staff ID (staff table)';


--
-- Name: COLUMN front_desk_check_ins.client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.front_desk_check_ins.client_id IS 'admins.id where type=client';


--
-- Name: COLUMN front_desk_check_ins.lead_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.front_desk_check_ins.lead_id IS 'admins.id where type=lead';


--
-- Name: COLUMN front_desk_check_ins.appointment_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.front_desk_check_ins.appointment_id IS 'booking_appointments.id';


--
-- Name: front_desk_check_ins_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.front_desk_check_ins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: front_desk_check_ins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.front_desk_check_ins_id_seq OWNED BY public.front_desk_check_ins.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(191) NOT NULL,
    name character varying(191) NOT NULL,
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
    queue character varying(191) NOT NULL,
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
-- Name: lead_matter_references; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lead_matter_references (
    id bigint NOT NULL,
    type character varying(50) NOT NULL,
    lead_id bigint NOT NULL,
    matter_id bigint NOT NULL,
    checklist_sent_at date,
    created_by bigint,
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: lead_matter_references_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lead_matter_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lead_matter_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lead_matter_references_id_seq OWNED BY public.lead_matter_references.id;


--
-- Name: lead_reminders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.lead_reminders (
    id bigint NOT NULL,
    visa_type character varying(50) NOT NULL,
    lead_id bigint NOT NULL,
    type character varying(20) NOT NULL,
    reminded_at timestamp(0) without time zone NOT NULL,
    reminded_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN lead_reminders.type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.lead_reminders.type IS 'email, sms, or phone';


--
-- Name: lead_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.lead_reminders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: lead_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.lead_reminders_id_seq OWNED BY public.lead_reminders.id;


--
-- Name: mail_report_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mail_report_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mail_report_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mail_report_attachments_id_seq OWNED BY public.email_log_attachments.id;


--
-- Name: mail_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mail_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mail_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mail_reports_id_seq OWNED BY public.email_logs.id;


--
-- Name: matter_checklists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matter_checklists (
    id bigint NOT NULL,
    matter_id bigint,
    name character varying(255),
    file character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: matter_checklists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matter_checklists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matter_checklists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matter_checklists_id_seq OWNED BY public.matter_checklists.id;


--
-- Name: matter_reminders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matter_reminders (
    id bigint NOT NULL,
    visa_type character varying(50) NOT NULL,
    client_matter_id bigint NOT NULL,
    type character varying(20) NOT NULL,
    reminded_at timestamp(0) without time zone NOT NULL,
    reminded_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN matter_reminders.type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.matter_reminders.type IS 'email, sms, or phone';


--
-- Name: COLUMN matter_reminders.reminded_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.matter_reminders.reminded_by IS 'Staff who sent the reminder';


--
-- Name: matter_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matter_reminders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matter_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matter_reminders_id_seq OWNED BY public.matter_reminders.id;


--
-- Name: matters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.matters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_for_company boolean DEFAULT false,
    workflow_id bigint,
    title character varying(255) DEFAULT ''::character varying NOT NULL,
    nick_name character varying(255),
    status smallint DEFAULT 1 NOT NULL,
    stream character varying(64)
);


--
-- Name: COLUMN matters.is_for_company; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.matters.is_for_company IS 'If true, this matter is only available for company clients. If false/null, available for personal clients.';


--
-- Name: matters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.matters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: matters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.matters_id_seq OWNED BY public.matters.id;


--
-- Name: message_attachments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_attachments (
    id bigint NOT NULL,
    message_id bigint NOT NULL,
    filename character varying(191) NOT NULL,
    original_filename character varying(191) NOT NULL,
    path character varying(191) NOT NULL,
    mime_type character varying(100) NOT NULL,
    type character varying(20) DEFAULT 'document'::character varying NOT NULL,
    size integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.message_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: message_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.message_attachments_id_seq OWNED BY public.message_attachments.id;


--
-- Name: message_recipients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.message_recipients (
    id bigint NOT NULL,
    message_id bigint NOT NULL,
    recipient_id bigint NOT NULL,
    recipient character varying(191),
    is_read boolean DEFAULT false NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: message_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.message_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: message_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.message_recipients_id_seq OWNED BY public.message_recipients.id;


--
-- Name: messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.messages (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.messages_id_seq OWNED BY public.messages.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(191) NOT NULL,
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
-- Name: nomination_document_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.nomination_document_types (
    id bigint NOT NULL,
    title character varying(191) NOT NULL,
    status smallint DEFAULT '1'::smallint NOT NULL,
    client_id bigint,
    client_matter_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: nomination_document_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.nomination_document_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: nomination_document_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.nomination_document_types_id_seq OWNED BY public.nomination_document_types.id;


--
-- Name: notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notes (
    id bigint NOT NULL,
    type character varying(191),
    status character varying(191),
    assigned_to bigint,
    is_action smallint,
    task_group character varying(191),
    action_date timestamp(0) without time zone,
    client_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    user_id bigint,
    lead_id bigint,
    unique_group_id character varying(191),
    title character varying(512),
    description text,
    note_deadline timestamp(0) without time zone,
    mail_id bigint,
    pin smallint DEFAULT '0'::smallint,
    matter_id bigint,
    mobile_number character varying(64)
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
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id bigint NOT NULL,
    receiver_id bigint,
    notification_type character varying(191),
    receiver_status smallint DEFAULT '0'::smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sender_id bigint,
    module_id bigint,
    url text,
    message text,
    seen smallint DEFAULT '0'::smallint NOT NULL,
    sender_status smallint DEFAULT '1'::smallint
);


--
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(191) NOT NULL,
    token character varying(191) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: personal_document_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_document_types (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    status smallint DEFAULT '1'::smallint NOT NULL,
    client_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_document_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_document_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_document_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_document_types_id_seq OWNED BY public.personal_document_types.id;


--
-- Name: phone_verifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.phone_verifications (
    id bigint NOT NULL,
    client_contact_id integer NOT NULL,
    client_id integer NOT NULL,
    phone character varying(20) NOT NULL,
    country_code character varying(10) NOT NULL,
    otp_code character varying(6) NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    verified_at timestamp(0) without time zone,
    verified_by integer,
    otp_sent_at timestamp(0) without time zone,
    otp_expires_at timestamp(0) without time zone,
    attempts integer DEFAULT 0 NOT NULL,
    max_attempts integer DEFAULT 3 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: phone_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.phone_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: phone_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.phone_verifications_id_seq OWNED BY public.phone_verifications.id;


--
-- Name: portal_document_checklists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.portal_document_checklists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: portal_document_checklists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.portal_document_checklists_id_seq OWNED BY public.document_checklists.id;


--
-- Name: refresh_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.refresh_tokens (
    id bigint NOT NULL,
    user_id integer NOT NULL,
    token character varying(500) NOT NULL,
    device_name character varying(191),
    expires_at timestamp(0) without time zone NOT NULL,
    is_revoked boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.refresh_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: refresh_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.refresh_tokens_id_seq OWNED BY public.refresh_tokens.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(191) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: signature_activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signature_activities (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    created_by bigint,
    action_type character varying(50) NOT NULL,
    note text,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: signature_activities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.signature_activities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: signature_activities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.signature_activities_id_seq OWNED BY public.signature_activities.id;


--
-- Name: signature_fields; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signature_fields (
    id bigint NOT NULL,
    document_id bigint NOT NULL,
    signer_id bigint,
    page_number smallint NOT NULL,
    x_position integer DEFAULT 0 NOT NULL,
    y_position integer DEFAULT 0 NOT NULL,
    x_percent double precision,
    y_percent double precision,
    width_percent double precision,
    height_percent double precision,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: signature_fields_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.signature_fields_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: signature_fields_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.signature_fields_id_seq OWNED BY public.signature_fields.id;


--
-- Name: signers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.signers (
    id bigint NOT NULL,
    document_id bigint,
    email character varying(191),
    name character varying(191),
    token character varying(64),
    status character varying(20),
    reminder_count integer DEFAULT 0,
    signed_at timestamp(0) without time zone,
    opened_at timestamp(0) without time zone,
    last_reminder_sent_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    email_template character varying(191),
    email_subject character varying(191),
    email_message text,
    from_email character varying(191),
    cancelled_at timestamp(0) without time zone
);


--
-- Name: signers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.signers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: signers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.signers_id_seq OWNED BY public.signers.id;


--
-- Name: sms_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sms_logs (
    id bigint NOT NULL,
    client_id bigint,
    client_contact_id bigint,
    sender_id bigint,
    recipient_phone character varying(20) NOT NULL,
    country_code character varying(10) DEFAULT '+61'::character varying NOT NULL,
    formatted_phone character varying(25),
    message_content text NOT NULL,
    message_type character varying(255) DEFAULT 'manual'::character varying NOT NULL,
    template_id bigint,
    provider character varying(20) NOT NULL,
    provider_message_id character varying(100),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    error_message text,
    cost numeric(10,4) DEFAULT '0'::numeric,
    sent_at timestamp(0) without time zone,
    delivered_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT sms_logs_message_type_check CHECK (((message_type)::text = ANY ((ARRAY['verification'::character varying, 'notification'::character varying, 'manual'::character varying, 'reminder'::character varying])::text[]))),
    CONSTRAINT sms_logs_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'sent'::character varying, 'delivered'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: COLUMN sms_logs.client_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.client_id IS 'Client who received SMS';


--
-- Name: COLUMN sms_logs.client_contact_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.client_contact_id IS 'Specific contact record';


--
-- Name: COLUMN sms_logs.sender_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.sender_id IS 'Admin user who sent SMS';


--
-- Name: COLUMN sms_logs.recipient_phone; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.recipient_phone IS 'Original phone number entered';


--
-- Name: COLUMN sms_logs.country_code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.country_code IS 'Country code';


--
-- Name: COLUMN sms_logs.formatted_phone; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.formatted_phone IS 'Final formatted number sent to provider';


--
-- Name: COLUMN sms_logs.message_content; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.message_content IS 'Full SMS message content';


--
-- Name: COLUMN sms_logs.message_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.message_type IS 'Type of SMS message';


--
-- Name: COLUMN sms_logs.template_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.template_id IS 'Template used if applicable';


--
-- Name: COLUMN sms_logs.provider; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.provider IS 'cellcast or twilio';


--
-- Name: COLUMN sms_logs.provider_message_id; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.provider_message_id IS 'Message ID from provider (SID)';


--
-- Name: COLUMN sms_logs.status; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.status IS 'Delivery status';


--
-- Name: COLUMN sms_logs.error_message; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.error_message IS 'Error details if failed';


--
-- Name: COLUMN sms_logs.cost; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.cost IS 'SMS cost';


--
-- Name: COLUMN sms_logs.sent_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.sent_at IS 'When SMS was sent to provider';


--
-- Name: COLUMN sms_logs.delivered_at; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_logs.delivered_at IS 'When SMS was delivered';


--
-- Name: sms_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sms_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sms_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sms_logs_id_seq OWNED BY public.sms_logs.id;


--
-- Name: sms_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sms_templates (
    id bigint NOT NULL,
    title character varying(100) NOT NULL,
    message text NOT NULL,
    variables text,
    category character varying(50),
    alias character varying(50),
    is_active boolean DEFAULT true NOT NULL,
    usage_count integer DEFAULT 0 NOT NULL,
    created_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    description text
);


--
-- Name: COLUMN sms_templates.title; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.title IS 'Template name';


--
-- Name: COLUMN sms_templates.message; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.message IS 'SMS message content with variables';


--
-- Name: COLUMN sms_templates.variables; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.variables IS 'Comma-separated list of variables';


--
-- Name: COLUMN sms_templates.category; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.category IS 'verification, reminder, notification, manual';


--
-- Name: COLUMN sms_templates.alias; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.alias IS 'Unique identifier for programmatic access';


--
-- Name: COLUMN sms_templates.is_active; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.is_active IS 'Whether template is active';


--
-- Name: COLUMN sms_templates.usage_count; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.usage_count IS 'Number of times template has been used';


--
-- Name: COLUMN sms_templates.created_by; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.created_by IS 'Admin user who created template';


--
-- Name: COLUMN sms_templates.description; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sms_templates.description IS 'Template description for internal reference';


--
-- Name: sms_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sms_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sms_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sms_templates_id_seq OWNED BY public.sms_templates.id;


--
-- Name: staff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff (
    id bigint NOT NULL,
    first_name character varying(191),
    last_name character varying(191),
    email character varying(191) NOT NULL,
    password character varying(191) NOT NULL,
    country_code character varying(20),
    phone character varying(100),
    status smallint DEFAULT '1'::smallint NOT NULL,
    role integer,
    "position" character varying(191),
    team character varying(191),
    permission text,
    office_id bigint,
    show_dashboard_per smallint DEFAULT '0'::smallint NOT NULL,
    time_zone character varying(50),
    marn_number character varying(100),
    legal_practitioner_number character varying(100),
    company_name character varying(191),
    company_website character varying(500),
    business_address text,
    business_phone character varying(100),
    business_mobile character varying(100),
    business_email character varying(191),
    tax_number character varying(100),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    quick_access_enabled boolean DEFAULT false NOT NULL,
    grant_super_admin_access boolean,
    is_solicitor smallint DEFAULT '0'::smallint NOT NULL,
    trust_rule42_supervisor boolean DEFAULT false NOT NULL
);


--
-- Name: staff_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: staff_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_id_seq OWNED BY public.staff.id;


--
-- Name: staff_login_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff_login_logs (
    id bigint CONSTRAINT user_logs_id_not_null NOT NULL,
    level character varying(50),
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    message text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: teams; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.teams (
    id bigint NOT NULL,
    name character varying(191),
    color character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- Name: trust_accounting_periods; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_accounting_periods (
    id bigint NOT NULL,
    period_start date NOT NULL,
    period_end date NOT NULL,
    status character varying(32) DEFAULT 'locked'::character varying NOT NULL,
    locked_at timestamp(0) without time zone,
    locked_by_staff_id bigint,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    unlocked_at timestamp(0) without time zone,
    unlocked_by_staff_id bigint,
    unlock_reason text
);


--
-- Name: trust_accounting_periods_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_accounting_periods_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_accounting_periods_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_accounting_periods_id_seq OWNED BY public.trust_accounting_periods.id;


--
-- Name: trust_audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_audit_logs (
    id bigint NOT NULL,
    table_name character varying(128) NOT NULL,
    row_id bigint NOT NULL,
    event character varying(64) NOT NULL,
    field_name character varying(128),
    old_value text,
    new_value text,
    performed_by bigint,
    ip_address character varying(45),
    context text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_audit_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_audit_logs_id_seq OWNED BY public.trust_audit_logs.id;


--
-- Name: trust_bank_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_bank_accounts (
    id bigint NOT NULL,
    name character varying(191) NOT NULL,
    bsb character varying(16),
    account_number_hint character varying(32),
    is_active boolean DEFAULT true NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_bank_accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_bank_accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_bank_accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_bank_accounts_id_seq OWNED BY public.trust_bank_accounts.id;


--
-- Name: trust_bank_statement_lines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_bank_statement_lines (
    id bigint NOT NULL,
    trust_bank_account_id bigint NOT NULL,
    value_date date NOT NULL,
    amount numeric(14,2) NOT NULL,
    narrative text,
    bank_reference character varying(500),
    matched_account_client_receipt_id bigint,
    matched_at timestamp(0) without time zone,
    matched_by_staff_id bigint,
    match_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_bank_statement_lines_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_bank_statement_lines_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_bank_statement_lines_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_bank_statement_lines_id_seq OWNED BY public.trust_bank_statement_lines.id;


--
-- Name: trust_monthly_archives; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_monthly_archives (
    id bigint NOT NULL,
    period_year smallint NOT NULL,
    period_month smallint NOT NULL,
    archive_type character varying(32) NOT NULL,
    prepared_by_staff_id bigint,
    prepared_at timestamp(0) without time zone,
    file_document_id bigint,
    status character varying(16) DEFAULT 'finalised'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_monthly_archives_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_monthly_archives_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_monthly_archives_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_monthly_archives_id_seq OWNED BY public.trust_monthly_archives.id;


--
-- Name: trust_practice_sequences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_practice_sequences (
    id bigint NOT NULL,
    trust_year_start_year smallint NOT NULL,
    last_sequence integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sequence_type character varying(8) DEFAULT 'TR'::character varying NOT NULL
);


--
-- Name: COLUMN trust_practice_sequences.trust_year_start_year; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.trust_practice_sequences.trust_year_start_year IS 'Victorian trust year begins 1 April; value is calendar year of 1 April (e.g. 2025 for Apr 2025–Mar 2026)';


--
-- Name: COLUMN trust_practice_sequences.sequence_type; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.trust_practice_sequences.sequence_type IS 'TR = trust receipt; TJ = trust journal';


--
-- Name: trust_practice_sequences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_practice_sequences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_practice_sequences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_practice_sequences_id_seq OWNED BY public.trust_practice_sequences.id;


--
-- Name: trust_withdrawal_authorities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_withdrawal_authorities (
    id bigint NOT NULL,
    account_client_receipt_id bigint NOT NULL,
    client_id bigint NOT NULL,
    invoice_no character varying(191),
    withdrawal_amount numeric(14,2) NOT NULL,
    authority_type_id bigint NOT NULL,
    authorised_by_staff_id bigint NOT NULL,
    notice_given_date date,
    authority_notes text,
    supervisor_override boolean DEFAULT false NOT NULL,
    override_reason text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_withdrawal_authorities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_withdrawal_authorities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_withdrawal_authorities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_withdrawal_authorities_id_seq OWNED BY public.trust_withdrawal_authorities.id;


--
-- Name: trust_withdrawal_authority_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_withdrawal_authority_types (
    id bigint NOT NULL,
    label character varying(191) NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: trust_withdrawal_authority_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_withdrawal_authority_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_withdrawal_authority_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_withdrawal_authority_types_id_seq OWNED BY public.trust_withdrawal_authority_types.id;


--
-- Name: user_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_logs_id_seq OWNED BY public.staff_login_logs.id;


--
-- Name: user_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_roles (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    name character varying(255),
    description text,
    module_access text
);


--
-- Name: user_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_roles_id_seq OWNED BY public.user_roles.id;


--
-- Name: visa_document_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.visa_document_types (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    status smallint DEFAULT '1'::smallint NOT NULL,
    client_id bigint,
    client_matter_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: visa_document_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.visa_document_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: visa_document_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.visa_document_types_id_seq OWNED BY public.visa_document_types.id;


--
-- Name: workflow_stages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflow_stages (
    id bigint NOT NULL,
    name character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sort_order integer,
    workflow_id bigint
);


--
-- Name: workflow_stages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflow_stages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflow_stages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflow_stages_id_seq OWNED BY public.workflow_stages.id;


--
-- Name: workflows; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.workflows (
    id bigint NOT NULL,
    name character varying(191) NOT NULL,
    status smallint DEFAULT '1'::smallint NOT NULL,
    matter_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: workflows_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.workflows_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: workflows_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.workflows_id_seq OWNED BY public.workflows.id;


--
-- Name: account_all_invoice_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_all_invoice_receipts ALTER COLUMN id SET DEFAULT nextval('public.account_all_invoice_receipts_id_seq'::regclass);


--
-- Name: account_client_receipts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_client_receipts ALTER COLUMN id SET DEFAULT nextval('public.account_client_receipts_id_seq'::regclass);


--
-- Name: activities_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities_logs ALTER COLUMN id SET DEFAULT nextval('public.activities_logs_id_seq'::regclass);


--
-- Name: admins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins ALTER COLUMN id SET DEFAULT nextval('public.admins_id_seq'::regclass);


--
-- Name: agent_details id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_details ALTER COLUMN id SET DEFAULT nextval('public.agent_details_id_seq'::regclass);


--
-- Name: appointment_consultants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_consultants ALTER COLUMN id SET DEFAULT nextval('public.appointment_consultants_id_seq'::regclass);


--
-- Name: appointment_payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_payments ALTER COLUMN id SET DEFAULT nextval('public.appointment_payments_id_seq'::regclass);


--
-- Name: appointment_sync_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_sync_logs ALTER COLUMN id SET DEFAULT nextval('public.appointment_sync_logs_id_seq'::regclass);


--
-- Name: booking_appointments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments ALTER COLUMN id SET DEFAULT nextval('public.booking_appointments_id_seq'::regclass);


--
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- Name: checkin_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checkin_history ALTER COLUMN id SET DEFAULT nextval('public.checkin_history_id_seq'::regclass);


--
-- Name: checkin_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checkin_logs ALTER COLUMN id SET DEFAULT nextval('public.checkin_logs_id_seq'::regclass);


--
-- Name: client_access_grants id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants ALTER COLUMN id SET DEFAULT nextval('public.client_access_grants_id_seq'::regclass);


--
-- Name: client_addresses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_addresses ALTER COLUMN id SET DEFAULT nextval('public.client_addresses_id_seq'::regclass);


--
-- Name: client_art_references id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references ALTER COLUMN id SET DEFAULT nextval('public.client_art_references_id_seq'::regclass);


--
-- Name: client_characters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_characters ALTER COLUMN id SET DEFAULT nextval('public.client_characters_id_seq'::regclass);


--
-- Name: client_contacts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_contacts ALTER COLUMN id SET DEFAULT nextval('public.client_contacts_id_seq'::regclass);


--
-- Name: client_court_hearings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_court_hearings ALTER COLUMN id SET DEFAULT nextval('public.client_court_hearings_id_seq'::regclass);


--
-- Name: client_emails id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails ALTER COLUMN id SET DEFAULT nextval('public.client_emails_id_seq'::regclass);


--
-- Name: client_experiences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_experiences ALTER COLUMN id SET DEFAULT nextval('public.client_experiences_id_seq'::regclass);


--
-- Name: client_legal_forms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_legal_forms ALTER COLUMN id SET DEFAULT nextval('public.client_legal_forms_id_seq'::regclass);


--
-- Name: client_matter_opposing_parties id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_opposing_parties ALTER COLUMN id SET DEFAULT nextval('public.client_matter_opposing_parties_id_seq'::regclass);


--
-- Name: client_matter_payment_forms_verifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications ALTER COLUMN id SET DEFAULT nextval('public.client_matter_payment_forms_verifications_id_seq'::regclass);


--
-- Name: client_matter_references id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references ALTER COLUMN id SET DEFAULT nextval('public.client_matter_references_id_seq'::regclass);


--
-- Name: client_matter_tasks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_tasks ALTER COLUMN id SET DEFAULT nextval('public.client_matter_tasks_id_seq'::regclass);


--
-- Name: client_matters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matters ALTER COLUMN id SET DEFAULT nextval('public.client_matters_id_seq'::regclass);


--
-- Name: client_occupations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_occupations ALTER COLUMN id SET DEFAULT nextval('public.client_occupations_id_seq'::regclass);


--
-- Name: client_passport_informations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_passport_informations ALTER COLUMN id SET DEFAULT nextval('public.client_passport_informations_id_seq'::regclass);


--
-- Name: client_qualifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_qualifications ALTER COLUMN id SET DEFAULT nextval('public.client_qualifications_id_seq'::regclass);


--
-- Name: client_relationships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_relationships ALTER COLUMN id SET DEFAULT nextval('public.client_relationships_id_seq'::regclass);


--
-- Name: client_spouse_details id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_spouse_details ALTER COLUMN id SET DEFAULT nextval('public.client_spouse_details_id_seq'::regclass);


--
-- Name: client_testscore id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_testscore ALTER COLUMN id SET DEFAULT nextval('public.client_testscore_id_seq'::regclass);


--
-- Name: client_travel_informations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_travel_informations ALTER COLUMN id SET DEFAULT nextval('public.client_travel_informations_id_seq'::regclass);


--
-- Name: client_visa_countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_visa_countries ALTER COLUMN id SET DEFAULT nextval('public.client_visa_countries_id_seq'::regclass);


--
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- Name: company_directors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_directors ALTER COLUMN id SET DEFAULT nextval('public.company_directors_id_seq'::regclass);


--
-- Name: company_nominations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_nominations ALTER COLUMN id SET DEFAULT nextval('public.company_nominations_id_seq'::regclass);


--
-- Name: company_sponsorships id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_sponsorships ALTER COLUMN id SET DEFAULT nextval('public.company_sponsorships_id_seq'::regclass);


--
-- Name: company_trading_names id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_trading_names ALTER COLUMN id SET DEFAULT nextval('public.company_trading_names_id_seq'::regclass);


--
-- Name: cost_assignment_forms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cost_assignment_forms ALTER COLUMN id SET DEFAULT nextval('public.cost_assignment_forms_id_seq'::regclass);


--
-- Name: countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN id SET DEFAULT nextval('public.countries_id_seq'::regclass);


--
-- Name: cp_doc_checklists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cp_doc_checklists ALTER COLUMN id SET DEFAULT nextval('public.application_document_lists_id_seq'::regclass);


--
-- Name: device_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_tokens ALTER COLUMN id SET DEFAULT nextval('public.device_tokens_id_seq'::regclass);


--
-- Name: disbursement_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.disbursement_lines ALTER COLUMN id SET DEFAULT nextval('public.disbursement_lines_id_seq'::regclass);


--
-- Name: document_checklists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_checklists ALTER COLUMN id SET DEFAULT nextval('public.portal_document_checklists_id_seq'::regclass);


--
-- Name: document_notes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_notes ALTER COLUMN id SET DEFAULT nextval('public.document_notes_id_seq'::regclass);


--
-- Name: documents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents ALTER COLUMN id SET DEFAULT nextval('public.documents_id_seq'::regclass);


--
-- Name: email_label_email_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_label_email_log ALTER COLUMN id SET DEFAULT nextval('public.email_label_mail_report_id_seq'::regclass);


--
-- Name: email_labels id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_labels ALTER COLUMN id SET DEFAULT nextval('public.email_labels_id_seq'::regclass);


--
-- Name: email_log_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_log_attachments ALTER COLUMN id SET DEFAULT nextval('public.mail_report_attachments_id_seq'::regclass);


--
-- Name: email_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs ALTER COLUMN id SET DEFAULT nextval('public.mail_reports_id_seq'::regclass);


--
-- Name: email_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates ALTER COLUMN id SET DEFAULT nextval('public.email_templates_id_seq'::regclass);


--
-- Name: email_verifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_verifications ALTER COLUMN id SET DEFAULT nextval('public.email_verifications_id_seq'::regclass);


--
-- Name: emails id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emails ALTER COLUMN id SET DEFAULT nextval('public.emails_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: front_desk_check_ins id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.front_desk_check_ins ALTER COLUMN id SET DEFAULT nextval('public.front_desk_check_ins_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: lead_matter_references id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references ALTER COLUMN id SET DEFAULT nextval('public.lead_matter_references_id_seq'::regclass);


--
-- Name: lead_reminders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_reminders ALTER COLUMN id SET DEFAULT nextval('public.lead_reminders_id_seq'::regclass);


--
-- Name: matter_checklists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_checklists ALTER COLUMN id SET DEFAULT nextval('public.matter_checklists_id_seq'::regclass);


--
-- Name: matter_reminders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_reminders ALTER COLUMN id SET DEFAULT nextval('public.matter_reminders_id_seq'::regclass);


--
-- Name: matters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matters ALTER COLUMN id SET DEFAULT nextval('public.matters_id_seq'::regclass);


--
-- Name: message_attachments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments ALTER COLUMN id SET DEFAULT nextval('public.message_attachments_id_seq'::regclass);


--
-- Name: message_recipients id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_recipients ALTER COLUMN id SET DEFAULT nextval('public.message_recipients_id_seq'::regclass);


--
-- Name: messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages ALTER COLUMN id SET DEFAULT nextval('public.messages_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: nomination_document_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomination_document_types ALTER COLUMN id SET DEFAULT nextval('public.nomination_document_types_id_seq'::regclass);


--
-- Name: notes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes ALTER COLUMN id SET DEFAULT nextval('public.notes_id_seq'::regclass);


--
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- Name: personal_document_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_document_types ALTER COLUMN id SET DEFAULT nextval('public.personal_document_types_id_seq'::regclass);


--
-- Name: phone_verifications id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.phone_verifications ALTER COLUMN id SET DEFAULT nextval('public.phone_verifications_id_seq'::regclass);


--
-- Name: refresh_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens ALTER COLUMN id SET DEFAULT nextval('public.refresh_tokens_id_seq'::regclass);


--
-- Name: signature_activities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_activities ALTER COLUMN id SET DEFAULT nextval('public.signature_activities_id_seq'::regclass);


--
-- Name: signature_fields id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_fields ALTER COLUMN id SET DEFAULT nextval('public.signature_fields_id_seq'::regclass);


--
-- Name: signers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signers ALTER COLUMN id SET DEFAULT nextval('public.signers_id_seq'::regclass);


--
-- Name: sms_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_logs ALTER COLUMN id SET DEFAULT nextval('public.sms_logs_id_seq'::regclass);


--
-- Name: sms_templates id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_templates ALTER COLUMN id SET DEFAULT nextval('public.sms_templates_id_seq'::regclass);


--
-- Name: staff id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff ALTER COLUMN id SET DEFAULT nextval('public.staff_id_seq'::regclass);


--
-- Name: staff_login_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_login_logs ALTER COLUMN id SET DEFAULT nextval('public.user_logs_id_seq'::regclass);


--
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- Name: trust_accounting_periods id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_accounting_periods ALTER COLUMN id SET DEFAULT nextval('public.trust_accounting_periods_id_seq'::regclass);


--
-- Name: trust_audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_audit_logs ALTER COLUMN id SET DEFAULT nextval('public.trust_audit_logs_id_seq'::regclass);


--
-- Name: trust_bank_accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_accounts ALTER COLUMN id SET DEFAULT nextval('public.trust_bank_accounts_id_seq'::regclass);


--
-- Name: trust_bank_statement_lines id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines ALTER COLUMN id SET DEFAULT nextval('public.trust_bank_statement_lines_id_seq'::regclass);


--
-- Name: trust_monthly_archives id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_monthly_archives ALTER COLUMN id SET DEFAULT nextval('public.trust_monthly_archives_id_seq'::regclass);


--
-- Name: trust_practice_sequences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_practice_sequences ALTER COLUMN id SET DEFAULT nextval('public.trust_practice_sequences_id_seq'::regclass);


--
-- Name: trust_withdrawal_authorities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities ALTER COLUMN id SET DEFAULT nextval('public.trust_withdrawal_authorities_id_seq'::regclass);


--
-- Name: trust_withdrawal_authority_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authority_types ALTER COLUMN id SET DEFAULT nextval('public.trust_withdrawal_authority_types_id_seq'::regclass);


--
-- Name: user_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles ALTER COLUMN id SET DEFAULT nextval('public.user_roles_id_seq'::regclass);


--
-- Name: visa_document_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.visa_document_types ALTER COLUMN id SET DEFAULT nextval('public.visa_document_types_id_seq'::regclass);


--
-- Name: workflow_stages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_stages ALTER COLUMN id SET DEFAULT nextval('public.workflow_stages_id_seq'::regclass);


--
-- Name: workflows id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflows ALTER COLUMN id SET DEFAULT nextval('public.workflows_id_seq'::regclass);


--
-- Name: account_all_invoice_receipts account_all_invoice_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_all_invoice_receipts
    ADD CONSTRAINT account_all_invoice_receipts_pkey PRIMARY KEY (id);


--
-- Name: account_client_receipts account_client_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_client_receipts
    ADD CONSTRAINT account_client_receipts_pkey PRIMARY KEY (id);


--
-- Name: activities_logs activities_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activities_logs
    ADD CONSTRAINT activities_logs_pkey PRIMARY KEY (id);


--
-- Name: admins admins_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_email_unique UNIQUE (email);


--
-- Name: admins admins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id);


--
-- Name: agent_details agent_details_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.agent_details
    ADD CONSTRAINT agent_details_pkey PRIMARY KEY (id);


--
-- Name: cp_doc_checklists application_document_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cp_doc_checklists
    ADD CONSTRAINT application_document_lists_pkey PRIMARY KEY (id);


--
-- Name: appointment_consultants appointment_consultants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_consultants
    ADD CONSTRAINT appointment_consultants_pkey PRIMARY KEY (id);


--
-- Name: appointment_payments appointment_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_payments
    ADD CONSTRAINT appointment_payments_pkey PRIMARY KEY (id);


--
-- Name: appointment_sync_logs appointment_sync_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.appointment_sync_logs
    ADD CONSTRAINT appointment_sync_logs_pkey PRIMARY KEY (id);


--
-- Name: booking_appointments booking_appointments_bansal_appointment_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_bansal_appointment_id_unique UNIQUE (bansal_appointment_id);


--
-- Name: booking_appointments booking_appointments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_pkey PRIMARY KEY (id);


--
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: checkin_history checkin_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checkin_history
    ADD CONSTRAINT checkin_history_pkey PRIMARY KEY (id);


--
-- Name: checkin_logs checkin_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.checkin_logs
    ADD CONSTRAINT checkin_logs_pkey PRIMARY KEY (id);


--
-- Name: client_access_grants client_access_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_pkey PRIMARY KEY (id);


--
-- Name: client_addresses client_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_addresses
    ADD CONSTRAINT client_addresses_pkey PRIMARY KEY (id);


--
-- Name: client_art_references client_art_references_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_pkey PRIMARY KEY (id);


--
-- Name: client_characters client_characters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_characters
    ADD CONSTRAINT client_characters_pkey PRIMARY KEY (id);


--
-- Name: client_contacts client_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_contacts
    ADD CONSTRAINT client_contacts_pkey PRIMARY KEY (id);


--
-- Name: client_court_hearings client_court_hearings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_court_hearings
    ADD CONSTRAINT client_court_hearings_pkey PRIMARY KEY (id);


--
-- Name: client_emails client_emails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_pkey PRIMARY KEY (id);


--
-- Name: client_experiences client_experiences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_experiences
    ADD CONSTRAINT client_experiences_pkey PRIMARY KEY (id);


--
-- Name: client_legal_forms client_legal_forms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_legal_forms
    ADD CONSTRAINT client_legal_forms_pkey PRIMARY KEY (id);


--
-- Name: client_matter_opposing_parties client_matter_opposing_parties_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_opposing_parties
    ADD CONSTRAINT client_matter_opposing_parties_pkey PRIMARY KEY (id);


--
-- Name: client_matter_payment_forms_verifications client_matter_payment_forms_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications
    ADD CONSTRAINT client_matter_payment_forms_verifications_pkey PRIMARY KEY (id);


--
-- Name: client_matter_references client_matter_ref_type_client_matter_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_ref_type_client_matter_unique UNIQUE (type, client_id, client_matter_id);


--
-- Name: client_matter_references client_matter_references_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_pkey PRIMARY KEY (id);


--
-- Name: client_matter_tasks client_matter_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_tasks
    ADD CONSTRAINT client_matter_tasks_pkey PRIMARY KEY (id);


--
-- Name: client_matters client_matters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matters
    ADD CONSTRAINT client_matters_pkey PRIMARY KEY (id);


--
-- Name: client_occupations client_occupations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_occupations
    ADD CONSTRAINT client_occupations_pkey PRIMARY KEY (id);


--
-- Name: client_passport_informations client_passport_informations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_passport_informations
    ADD CONSTRAINT client_passport_informations_pkey PRIMARY KEY (id);


--
-- Name: client_qualifications client_qualifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_qualifications
    ADD CONSTRAINT client_qualifications_pkey PRIMARY KEY (id);


--
-- Name: client_relationships client_relationships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_relationships
    ADD CONSTRAINT client_relationships_pkey PRIMARY KEY (id);


--
-- Name: client_spouse_details client_spouse_details_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_spouse_details
    ADD CONSTRAINT client_spouse_details_pkey PRIMARY KEY (id);


--
-- Name: client_testscore client_testscore_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_testscore
    ADD CONSTRAINT client_testscore_pkey PRIMARY KEY (id);


--
-- Name: client_travel_informations client_travel_informations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_travel_informations
    ADD CONSTRAINT client_travel_informations_pkey PRIMARY KEY (id);


--
-- Name: client_visa_countries client_visa_countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_visa_countries
    ADD CONSTRAINT client_visa_countries_pkey PRIMARY KEY (id);


--
-- Name: companies companies_admin_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_admin_id_unique UNIQUE (admin_id);


--
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- Name: company_directors company_directors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_pkey PRIMARY KEY (id);


--
-- Name: company_nominations company_nominations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_pkey PRIMARY KEY (id);


--
-- Name: company_sponsorships company_sponsorships_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_sponsorships
    ADD CONSTRAINT company_sponsorships_pkey PRIMARY KEY (id);


--
-- Name: company_trading_names company_trading_names_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_trading_names
    ADD CONSTRAINT company_trading_names_pkey PRIMARY KEY (id);


--
-- Name: cost_assignment_forms cost_assignment_forms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cost_assignment_forms
    ADD CONSTRAINT cost_assignment_forms_pkey PRIMARY KEY (id);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (id);


--
-- Name: countries countries_sortname_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_sortname_unique UNIQUE (sortname);


--
-- Name: device_tokens device_tokens_device_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_device_token_unique UNIQUE (device_token);


--
-- Name: device_tokens device_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_pkey PRIMARY KEY (id);


--
-- Name: disbursement_lines disbursement_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.disbursement_lines
    ADD CONSTRAINT disbursement_lines_pkey PRIMARY KEY (id);


--
-- Name: document_notes document_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_notes
    ADD CONSTRAINT document_notes_pkey PRIMARY KEY (id);


--
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- Name: email_label_email_log email_label_mail_report_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_label_email_log
    ADD CONSTRAINT email_label_mail_report_pkey PRIMARY KEY (id);


--
-- Name: email_labels email_labels_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_labels
    ADD CONSTRAINT email_labels_pkey PRIMARY KEY (id);


--
-- Name: email_label_email_log email_log_label_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_label_email_log
    ADD CONSTRAINT email_log_label_unique UNIQUE (email_log_id, email_label_id);


--
-- Name: email_templates email_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_pkey PRIMARY KEY (id);


--
-- Name: email_verifications email_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_verifications
    ADD CONSTRAINT email_verifications_pkey PRIMARY KEY (id);


--
-- Name: emails emails_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emails
    ADD CONSTRAINT emails_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: front_desk_check_ins front_desk_check_ins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.front_desk_check_ins
    ADD CONSTRAINT front_desk_check_ins_pkey PRIMARY KEY (id);


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
-- Name: lead_matter_references lead_matter_ref_type_lead_matter_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_ref_type_lead_matter_unique UNIQUE (type, lead_id, matter_id);


--
-- Name: lead_matter_references lead_matter_references_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_pkey PRIMARY KEY (id);


--
-- Name: lead_reminders lead_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_pkey PRIMARY KEY (id);


--
-- Name: email_log_attachments mail_report_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_log_attachments
    ADD CONSTRAINT mail_report_attachments_pkey PRIMARY KEY (id);


--
-- Name: email_logs mail_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_logs
    ADD CONSTRAINT mail_reports_pkey PRIMARY KEY (id);


--
-- Name: matter_checklists matter_checklists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_checklists
    ADD CONSTRAINT matter_checklists_pkey PRIMARY KEY (id);


--
-- Name: matter_reminders matter_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_pkey PRIMARY KEY (id);


--
-- Name: matters matters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matters
    ADD CONSTRAINT matters_pkey PRIMARY KEY (id);


--
-- Name: message_attachments message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_pkey PRIMARY KEY (id);


--
-- Name: message_recipients message_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_recipients
    ADD CONSTRAINT message_recipients_pkey PRIMARY KEY (id);


--
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: nomination_document_types nomination_document_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.nomination_document_types
    ADD CONSTRAINT nomination_document_types_pkey PRIMARY KEY (id);


--
-- Name: notes notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_document_types personal_document_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_document_types
    ADD CONSTRAINT personal_document_types_pkey PRIMARY KEY (id);


--
-- Name: phone_verifications phone_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.phone_verifications
    ADD CONSTRAINT phone_verifications_pkey PRIMARY KEY (id);


--
-- Name: document_checklists portal_document_checklists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.document_checklists
    ADD CONSTRAINT portal_document_checklists_pkey PRIMARY KEY (id);


--
-- Name: refresh_tokens refresh_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_pkey PRIMARY KEY (id);


--
-- Name: refresh_tokens refresh_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_token_unique UNIQUE (token);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: signature_activities signature_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_activities
    ADD CONSTRAINT signature_activities_pkey PRIMARY KEY (id);


--
-- Name: signature_fields signature_fields_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_fields
    ADD CONSTRAINT signature_fields_pkey PRIMARY KEY (id);


--
-- Name: signers signers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signers
    ADD CONSTRAINT signers_pkey PRIMARY KEY (id);


--
-- Name: sms_logs sms_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_logs
    ADD CONSTRAINT sms_logs_pkey PRIMARY KEY (id);


--
-- Name: sms_templates sms_templates_alias_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_templates
    ADD CONSTRAINT sms_templates_alias_unique UNIQUE (alias);


--
-- Name: sms_templates sms_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sms_templates
    ADD CONSTRAINT sms_templates_pkey PRIMARY KEY (id);


--
-- Name: staff staff_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_email_unique UNIQUE (email);


--
-- Name: staff staff_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_pkey PRIMARY KEY (id);


--
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);


--
-- Name: trust_accounting_periods trust_accounting_periods_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_accounting_periods
    ADD CONSTRAINT trust_accounting_periods_pkey PRIMARY KEY (id);


--
-- Name: trust_monthly_archives trust_archive_period_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_monthly_archives
    ADD CONSTRAINT trust_archive_period_type_unique UNIQUE (period_year, period_month, archive_type);


--
-- Name: trust_audit_logs trust_audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_audit_logs
    ADD CONSTRAINT trust_audit_logs_pkey PRIMARY KEY (id);


--
-- Name: trust_bank_accounts trust_bank_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_accounts
    ADD CONSTRAINT trust_bank_accounts_pkey PRIMARY KEY (id);


--
-- Name: trust_bank_statement_lines trust_bank_statement_lines_matched_account_client_receipt_id_un; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines
    ADD CONSTRAINT trust_bank_statement_lines_matched_account_client_receipt_id_un UNIQUE (matched_account_client_receipt_id);


--
-- Name: trust_bank_statement_lines trust_bank_statement_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines
    ADD CONSTRAINT trust_bank_statement_lines_pkey PRIMARY KEY (id);


--
-- Name: trust_monthly_archives trust_monthly_archives_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_monthly_archives
    ADD CONSTRAINT trust_monthly_archives_pkey PRIMARY KEY (id);


--
-- Name: trust_practice_sequences trust_practice_sequences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_practice_sequences
    ADD CONSTRAINT trust_practice_sequences_pkey PRIMARY KEY (id);


--
-- Name: trust_practice_sequences trust_seq_year_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_practice_sequences
    ADD CONSTRAINT trust_seq_year_type_unique UNIQUE (trust_year_start_year, sequence_type);


--
-- Name: trust_withdrawal_authorities trust_withdrawal_authorities_account_client_receipt_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities
    ADD CONSTRAINT trust_withdrawal_authorities_account_client_receipt_id_unique UNIQUE (account_client_receipt_id);


--
-- Name: trust_withdrawal_authorities trust_withdrawal_authorities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities
    ADD CONSTRAINT trust_withdrawal_authorities_pkey PRIMARY KEY (id);


--
-- Name: trust_withdrawal_authority_types trust_withdrawal_authority_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authority_types
    ADD CONSTRAINT trust_withdrawal_authority_types_pkey PRIMARY KEY (id);


--
-- Name: staff_login_logs user_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_login_logs
    ADD CONSTRAINT user_logs_pkey PRIMARY KEY (id);


--
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (id);


--
-- Name: visa_document_types visa_document_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.visa_document_types
    ADD CONSTRAINT visa_document_types_pkey PRIMARY KEY (id);


--
-- Name: workflow_stages workflow_stages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflow_stages
    ADD CONSTRAINT workflow_stages_pkey PRIMARY KEY (id);


--
-- Name: workflows workflows_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.workflows
    ADD CONSTRAINT workflows_pkey PRIMARY KEY (id);


--
-- Name: account_all_invoice_receipts_receipt_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_all_invoice_receipts_receipt_id_index ON public.account_all_invoice_receipts USING btree (receipt_id);


--
-- Name: account_all_invoice_receipts_receipt_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_all_invoice_receipts_receipt_type_index ON public.account_all_invoice_receipts USING btree (receipt_type);


--
-- Name: account_client_receipts_pdf_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_client_receipts_pdf_document_id_index ON public.account_client_receipts USING btree (pdf_document_id);


--
-- Name: account_client_receipts_receipt_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_client_receipts_receipt_type_index ON public.account_client_receipts USING btree (receipt_type);


--
-- Name: activities_logs_activity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_activity_type_index ON public.activities_logs USING btree (activity_type);


--
-- Name: activities_logs_client_id_activity_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_client_id_activity_type_index ON public.activities_logs USING btree (client_id, activity_type);


--
-- Name: activities_logs_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_client_id_index ON public.activities_logs USING btree (client_id);


--
-- Name: activities_logs_client_id_source_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_client_id_source_index ON public.activities_logs USING btree (client_id, source);


--
-- Name: activities_logs_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_created_by_index ON public.activities_logs USING btree (created_by);


--
-- Name: activities_logs_sms_log_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activities_logs_sms_log_id_index ON public.activities_logs USING btree (sms_log_id);


--
-- Name: admins_archived_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admins_archived_by_index ON public.admins USING btree (archived_by);


--
-- Name: admins_dob_verified_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admins_dob_verified_by_index ON public.admins USING btree (dob_verified_by);


--
-- Name: admins_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admins_type_index ON public.admins USING btree (type);


--
-- Name: admins_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX admins_user_id_index ON public.admins USING btree (user_id);


--
-- Name: agent_details_agent_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_details_agent_name_index ON public.agent_details USING btree (agent_name);


--
-- Name: agent_details_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX agent_details_status_index ON public.agent_details USING btree (status);


--
-- Name: appointment_payments_appointment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_appointment_id_index ON public.appointment_payments USING btree (appointment_id);


--
-- Name: appointment_payments_charge_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_charge_id_index ON public.appointment_payments USING btree (charge_id);


--
-- Name: appointment_payments_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_created_at_index ON public.appointment_payments USING btree (created_at);


--
-- Name: appointment_payments_customer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_customer_id_index ON public.appointment_payments USING btree (customer_id);


--
-- Name: appointment_payments_payment_gateway_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_payment_gateway_index ON public.appointment_payments USING btree (payment_gateway);


--
-- Name: appointment_payments_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_status_index ON public.appointment_payments USING btree (status);


--
-- Name: appointment_payments_transaction_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_payments_transaction_id_index ON public.appointment_payments USING btree (transaction_id);


--
-- Name: appointment_sync_logs_started_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_sync_logs_started_at_index ON public.appointment_sync_logs USING btree (started_at);


--
-- Name: appointment_sync_logs_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_sync_logs_status_index ON public.appointment_sync_logs USING btree (status);


--
-- Name: appointment_sync_logs_sync_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX appointment_sync_logs_sync_type_index ON public.appointment_sync_logs USING btree (sync_type);


--
-- Name: booking_appointments_appointment_datetime_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_appointment_datetime_index ON public.booking_appointments USING btree (appointment_datetime);


--
-- Name: booking_appointments_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_client_id_index ON public.booking_appointments USING btree (client_id);


--
-- Name: booking_appointments_consultant_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_consultant_id_index ON public.booking_appointments USING btree (consultant_id);


--
-- Name: booking_appointments_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_created_at_index ON public.booking_appointments USING btree (created_at);


--
-- Name: booking_appointments_location_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_location_index ON public.booking_appointments USING btree (location);


--
-- Name: booking_appointments_service_id_noe_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_service_id_noe_id_index ON public.booking_appointments USING btree (service_id, noe_id);


--
-- Name: booking_appointments_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_status_index ON public.booking_appointments USING btree (status);


--
-- Name: booking_appointments_sync_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_sync_status_index ON public.booking_appointments USING btree (sync_status);


--
-- Name: booking_appointments_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX booking_appointments_user_id_index ON public.booking_appointments USING btree (user_id);


--
-- Name: checkin_history_checkin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checkin_history_checkin_id_index ON public.checkin_history USING btree (checkin_id);


--
-- Name: checkin_history_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checkin_history_created_by_index ON public.checkin_history USING btree (created_by);


--
-- Name: checkin_logs_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checkin_logs_client_id_index ON public.checkin_logs USING btree (client_id);


--
-- Name: checkin_logs_office_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checkin_logs_office_index ON public.checkin_logs USING btree (office);


--
-- Name: checkin_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX checkin_logs_user_id_index ON public.checkin_logs USING btree (user_id);


--
-- Name: client_addresses_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_addresses_admin_id_index ON public.client_addresses USING btree (admin_id);


--
-- Name: client_addresses_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_addresses_client_id_index ON public.client_addresses USING btree (client_id);


--
-- Name: client_addresses_country_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_addresses_country_index ON public.client_addresses USING btree (country);


--
-- Name: client_addresses_suburb_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_addresses_suburb_index ON public.client_addresses USING btree (suburb);


--
-- Name: client_art_references_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_art_references_client_id_index ON public.client_art_references USING btree (client_id);


--
-- Name: client_art_references_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_art_references_client_matter_id_index ON public.client_art_references USING btree (client_matter_id);


--
-- Name: client_art_references_is_pinned_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_art_references_is_pinned_index ON public.client_art_references USING btree (is_pinned);


--
-- Name: client_characters_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_characters_admin_id_index ON public.client_characters USING btree (admin_id);


--
-- Name: client_characters_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_characters_client_id_index ON public.client_characters USING btree (client_id);


--
-- Name: client_characters_type_of_character_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_characters_type_of_character_index ON public.client_characters USING btree (type_of_character);


--
-- Name: client_contacts_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_contacts_admin_id_index ON public.client_contacts USING btree (admin_id);


--
-- Name: client_contacts_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_contacts_client_id_index ON public.client_contacts USING btree (client_id);


--
-- Name: client_contacts_is_verified_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_contacts_is_verified_index ON public.client_contacts USING btree (is_verified);


--
-- Name: client_court_hearings_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_court_hearings_client_id_index ON public.client_court_hearings USING btree (client_id);


--
-- Name: client_court_hearings_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_court_hearings_client_matter_id_index ON public.client_court_hearings USING btree (client_matter_id);


--
-- Name: client_court_hearings_hearing_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_court_hearings_hearing_date_index ON public.client_court_hearings USING btree (hearing_date);


--
-- Name: client_emails_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_admin_id_index ON public.client_emails USING btree (admin_id);


--
-- Name: client_emails_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_client_id_index ON public.client_emails USING btree (client_id);


--
-- Name: client_emails_is_verified_verification_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_is_verified_verification_token_index ON public.client_emails USING btree (is_verified, verification_token);


--
-- Name: client_emails_token_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_emails_token_expires_at_index ON public.client_emails USING btree (token_expires_at);


--
-- Name: client_experiences_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_experiences_admin_id_index ON public.client_experiences USING btree (admin_id);


--
-- Name: client_experiences_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_experiences_client_id_index ON public.client_experiences USING btree (client_id);


--
-- Name: client_experiences_job_finish_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_experiences_job_finish_date_index ON public.client_experiences USING btree (job_finish_date);


--
-- Name: client_legal_forms_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_legal_forms_client_id_index ON public.client_legal_forms USING btree (client_id);


--
-- Name: client_legal_forms_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_legal_forms_client_matter_id_index ON public.client_legal_forms USING btree (client_matter_id);


--
-- Name: client_legal_forms_form_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_legal_forms_form_type_index ON public.client_legal_forms USING btree (form_type);


--
-- Name: client_matter_opposing_parties_client_matter_id_sort_order_inde; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_opposing_parties_client_matter_id_sort_order_inde ON public.client_matter_opposing_parties USING btree (client_matter_id, sort_order);


--
-- Name: client_matter_payment_forms_verifications_client_matter_id_inde; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_payment_forms_verifications_client_matter_id_inde ON public.client_matter_payment_forms_verifications USING btree (client_matter_id);


--
-- Name: client_matter_references_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_references_client_id_index ON public.client_matter_references USING btree (client_id);


--
-- Name: client_matter_references_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_references_client_matter_id_index ON public.client_matter_references USING btree (client_matter_id);


--
-- Name: client_matter_references_is_pinned_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_references_is_pinned_index ON public.client_matter_references USING btree (is_pinned);


--
-- Name: client_matter_references_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_references_type_index ON public.client_matter_references USING btree (type);


--
-- Name: client_matter_tasks_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_tasks_client_id_index ON public.client_matter_tasks USING btree (client_id);


--
-- Name: client_matter_tasks_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matter_tasks_client_matter_id_index ON public.client_matter_tasks USING btree (client_matter_id);


--
-- Name: client_matters_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_client_id_index ON public.client_matters USING btree (client_id);


--
-- Name: client_matters_sel_legal_practitioner_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_sel_legal_practitioner_index ON public.client_matters USING btree (sel_legal_practitioner);


--
-- Name: client_matters_sel_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_sel_matter_id_index ON public.client_matters USING btree (sel_matter_id);


--
-- Name: client_matters_sel_person_assisting_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_sel_person_assisting_index ON public.client_matters USING btree (sel_person_assisting);


--
-- Name: client_matters_sel_person_responsible_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_sel_person_responsible_index ON public.client_matters USING btree (sel_person_responsible);


--
-- Name: client_matters_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_user_id_index ON public.client_matters USING btree (user_id);


--
-- Name: client_matters_workflow_stage_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_matters_workflow_stage_id_index ON public.client_matters USING btree (workflow_stage_id);


--
-- Name: client_occupations_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_occupations_admin_id_index ON public.client_occupations USING btree (admin_id);


--
-- Name: client_occupations_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_occupations_client_id_index ON public.client_occupations USING btree (client_id);


--
-- Name: client_passport_informations_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_passport_informations_admin_id_index ON public.client_passport_informations USING btree (admin_id);


--
-- Name: client_passport_informations_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_passport_informations_client_id_index ON public.client_passport_informations USING btree (client_id);


--
-- Name: client_qualifications_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_qualifications_admin_id_index ON public.client_qualifications USING btree (admin_id);


--
-- Name: client_qualifications_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_qualifications_client_id_index ON public.client_qualifications USING btree (client_id);


--
-- Name: client_qualifications_finish_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_qualifications_finish_date_index ON public.client_qualifications USING btree (finish_date);


--
-- Name: client_relationships_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_relationships_admin_id_index ON public.client_relationships USING btree (admin_id);


--
-- Name: client_relationships_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_relationships_client_id_index ON public.client_relationships USING btree (client_id);


--
-- Name: client_relationships_related_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_relationships_related_client_id_index ON public.client_relationships USING btree (related_client_id);


--
-- Name: client_relationships_relationship_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_relationships_relationship_type_index ON public.client_relationships USING btree (relationship_type);


--
-- Name: client_spouse_details_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_spouse_details_client_id_index ON public.client_spouse_details USING btree (client_id);


--
-- Name: client_testscore_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_testscore_admin_id_index ON public.client_testscore USING btree (admin_id);


--
-- Name: client_testscore_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_testscore_client_id_index ON public.client_testscore USING btree (client_id);


--
-- Name: client_testscore_test_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_testscore_test_date_index ON public.client_testscore USING btree (test_date);


--
-- Name: client_testscore_test_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_testscore_test_type_index ON public.client_testscore USING btree (test_type);


--
-- Name: client_travel_informations_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_travel_informations_admin_id_index ON public.client_travel_informations USING btree (admin_id);


--
-- Name: client_travel_informations_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_travel_informations_client_id_index ON public.client_travel_informations USING btree (client_id);


--
-- Name: client_visa_countries_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_visa_countries_admin_id_index ON public.client_visa_countries USING btree (admin_id);


--
-- Name: client_visa_countries_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_visa_countries_client_id_index ON public.client_visa_countries USING btree (client_id);


--
-- Name: client_visa_countries_visa_expiry_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_visa_countries_visa_expiry_date_index ON public.client_visa_countries USING btree (visa_expiry_date);


--
-- Name: client_visa_countries_visa_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX client_visa_countries_visa_type_index ON public.client_visa_countries USING btree (visa_type);


--
-- Name: companies_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_admin_id_index ON public.companies USING btree (admin_id);


--
-- Name: companies_company_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_company_name_index ON public.companies USING btree (company_name);


--
-- Name: companies_contact_person_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX companies_contact_person_id_index ON public.companies USING btree (contact_person_id);


--
-- Name: company_directors_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_directors_company_id_index ON public.company_directors USING btree (company_id);


--
-- Name: company_nominations_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_nominations_company_id_index ON public.company_nominations USING btree (company_id);


--
-- Name: company_sponsorships_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_sponsorships_company_id_index ON public.company_sponsorships USING btree (company_id);


--
-- Name: company_trading_names_company_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX company_trading_names_company_id_index ON public.company_trading_names USING btree (company_id);


--
-- Name: cost_assignment_forms_agent_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cost_assignment_forms_agent_id_index ON public.cost_assignment_forms USING btree (agent_id);


--
-- Name: cost_assignment_forms_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cost_assignment_forms_client_id_index ON public.cost_assignment_forms USING btree (client_id);


--
-- Name: cost_assignment_forms_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cost_assignment_forms_client_matter_id_index ON public.cost_assignment_forms USING btree (client_matter_id);


--
-- Name: device_tokens_device_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX device_tokens_device_token_index ON public.device_tokens USING btree (device_token);


--
-- Name: device_tokens_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX device_tokens_user_id_is_active_index ON public.device_tokens USING btree (user_id, is_active);


--
-- Name: disbursement_lines_cost_assignment_form_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX disbursement_lines_cost_assignment_form_id_index ON public.disbursement_lines USING btree (cost_assignment_form_id);


--
-- Name: document_notes_action_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_notes_action_type_index ON public.document_notes USING btree (action_type);


--
-- Name: document_notes_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_notes_created_at_index ON public.document_notes USING btree (created_at);


--
-- Name: document_notes_created_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_notes_created_by_index ON public.document_notes USING btree (created_by);


--
-- Name: document_notes_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX document_notes_document_id_index ON public.document_notes USING btree (document_id);


--
-- Name: documents_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_client_id_index ON public.documents USING btree (client_id);


--
-- Name: documents_doc_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_doc_type_index ON public.documents USING btree (doc_type);


--
-- Name: documents_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_type_index ON public.documents USING btree (type);


--
-- Name: documents_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX documents_user_id_index ON public.documents USING btree (user_id);


--
-- Name: email_label_mail_report_email_label_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_label_mail_report_email_label_id_index ON public.email_label_email_log USING btree (email_label_id);


--
-- Name: email_label_mail_report_mail_report_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_label_mail_report_mail_report_id_index ON public.email_label_email_log USING btree (email_log_id);


--
-- Name: email_labels_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_labels_is_active_index ON public.email_labels USING btree (is_active);


--
-- Name: email_labels_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_labels_type_index ON public.email_labels USING btree (type);


--
-- Name: email_labels_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_labels_user_id_index ON public.email_labels USING btree (user_id);


--
-- Name: email_logs_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_client_id_index ON public.email_logs USING btree (client_id);


--
-- Name: email_logs_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_client_matter_id_index ON public.email_logs USING btree (client_matter_id);


--
-- Name: email_logs_mail_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_mail_type_index ON public.email_logs USING btree (mail_type);


--
-- Name: email_logs_reciept_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_reciept_id_index ON public.email_logs USING btree (reciept_id);


--
-- Name: email_logs_template_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_template_id_index ON public.email_logs USING btree (template_id);


--
-- Name: email_logs_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_type_index ON public.email_logs USING btree (type);


--
-- Name: email_logs_uploaded_doc_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_uploaded_doc_id_index ON public.email_logs USING btree (uploaded_doc_id);


--
-- Name: email_logs_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_logs_user_id_index ON public.email_logs USING btree (user_id);


--
-- Name: email_templates_alias_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_templates_alias_index ON public.email_templates USING btree (alias);


--
-- Name: email_templates_matter_first_unique; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX email_templates_matter_first_unique ON public.email_templates USING btree (matter_id) WHERE ((type)::text = 'matter_first'::text);


--
-- Name: email_templates_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_templates_matter_id_index ON public.email_templates USING btree (matter_id);


--
-- Name: email_templates_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_templates_type_index ON public.email_templates USING btree (type);


--
-- Name: email_templates_type_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_templates_type_matter_id_index ON public.email_templates USING btree (type, matter_id);


--
-- Name: email_verifications_client_email_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_verifications_client_email_id_index ON public.email_verifications USING btree (client_email_id);


--
-- Name: email_verifications_email_token_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_verifications_email_token_sent_at_index ON public.email_verifications USING btree (email, token_sent_at);


--
-- Name: email_verifications_is_verified_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_verifications_is_verified_index ON public.email_verifications USING btree (is_verified);


--
-- Name: email_verifications_token_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_verifications_token_expires_at_index ON public.email_verifications USING btree (token_expires_at);


--
-- Name: email_verifications_verification_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX email_verifications_verification_token_index ON public.email_verifications USING btree (verification_token);


--
-- Name: emails_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX emails_email_index ON public.emails USING btree (email);


--
-- Name: emails_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX emails_status_index ON public.emails USING btree (status);


--
-- Name: front_desk_check_ins_admin_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX front_desk_check_ins_admin_id_index ON public.front_desk_check_ins USING btree (admin_id);


--
-- Name: front_desk_check_ins_appointment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX front_desk_check_ins_appointment_id_index ON public.front_desk_check_ins USING btree (appointment_id);


--
-- Name: front_desk_check_ins_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX front_desk_check_ins_client_id_index ON public.front_desk_check_ins USING btree (client_id);


--
-- Name: front_desk_check_ins_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX front_desk_check_ins_created_at_index ON public.front_desk_check_ins USING btree (created_at);


--
-- Name: front_desk_check_ins_lead_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX front_desk_check_ins_lead_id_index ON public.front_desk_check_ins USING btree (lead_id);


--
-- Name: idx_admins_is_company; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_admins_is_company ON public.admins USING btree (is_company) WHERE (is_company = true);


--
-- Name: idx_art_client_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_art_client_status ON public.client_art_references USING btree (client_id, status_of_file);


--
-- Name: idx_art_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_art_status ON public.client_art_references USING btree (status_of_file);


--
-- Name: idx_art_submission_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_art_submission_date ON public.client_art_references USING btree (submission_last_date);


--
-- Name: idx_cag_approver_queue; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cag_approver_queue ON public.client_access_grants USING btree (status, approved_by_staff_id);


--
-- Name: idx_cag_ends_at; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cag_ends_at ON public.client_access_grants USING btree (ends_at) WHERE ((status)::text = 'active'::text);


--
-- Name: idx_cag_staff_admin_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cag_staff_admin_status ON public.client_access_grants USING btree (staff_id, admin_id, status);


--
-- Name: idx_cag_status_requested; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_cag_status_requested ON public.client_access_grants USING btree (status, requested_at);


--
-- Name: idx_calendar_type; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_calendar_type ON public.appointment_consultants USING btree (calendar_type);


--
-- Name: idx_client_country; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_client_country ON public.client_qualifications USING btree (client_id, country);


--
-- Name: idx_client_experiences_client_job_country; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_client_experiences_client_job_country ON public.client_experiences USING btree (client_id, job_country);


--
-- Name: idx_companies_contact_person_id; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_companies_contact_person_id ON public.companies USING btree (contact_person_id) WHERE (contact_person_id IS NOT NULL);


--
-- Name: idx_documents_office; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_documents_office ON public.documents USING btree (office_id);


--
-- Name: idx_is_active; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_is_active ON public.appointment_consultants USING btree (is_active);


--
-- Name: idx_matters_is_for_company; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_matters_is_for_company ON public.matters USING btree (is_for_company) WHERE (is_for_company = true);


--
-- Name: idx_matters_office; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_matters_office ON public.client_matters USING btree (office_id);


--
-- Name: idx_matters_office_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_matters_office_status ON public.client_matters USING btree (office_id, matter_status);


--
-- Name: idx_notes_action_assigned; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notes_action_assigned ON public.notes USING btree (type, status, assigned_to, is_action);


--
-- Name: idx_notes_client_tasks; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notes_client_tasks ON public.notes USING btree (type, client_id, is_action);


--
-- Name: idx_notes_completed_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notes_completed_date ON public.notes USING btree (type, status, action_date);


--
-- Name: idx_notes_task_group_date; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notes_task_group_date ON public.notes USING btree (type, task_group, action_date);


--
-- Name: idx_notifications_receiver_type_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notifications_receiver_type_status ON public.notifications USING btree (receiver_id, notification_type, receiver_status, created_at);


--
-- Name: idx_notifications_type_receiver_status; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_notifications_type_receiver_status ON public.notifications USING btree (notification_type, receiver_id, receiver_status, created_at);


--
-- Name: idx_spouse_client; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_spouse_client ON public.client_spouse_details USING btree (client_id);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: lead_matter_references_lead_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_matter_references_lead_id_index ON public.lead_matter_references USING btree (lead_id);


--
-- Name: lead_matter_references_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_matter_references_matter_id_index ON public.lead_matter_references USING btree (matter_id);


--
-- Name: lead_matter_references_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_matter_references_type_index ON public.lead_matter_references USING btree (type);


--
-- Name: lead_reminders_lead_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_reminders_lead_id_index ON public.lead_reminders USING btree (lead_id);


--
-- Name: lead_reminders_visa_lead_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_reminders_visa_lead_type_idx ON public.lead_reminders USING btree (visa_type, lead_id, type);


--
-- Name: lead_reminders_visa_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX lead_reminders_visa_type_index ON public.lead_reminders USING btree (visa_type);


--
-- Name: mail_report_attachments_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_report_attachments_created_at_index ON public.email_log_attachments USING btree (created_at);


--
-- Name: mail_report_attachments_is_inline_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_report_attachments_is_inline_index ON public.email_log_attachments USING btree (is_inline);


--
-- Name: mail_report_attachments_mail_report_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_report_attachments_mail_report_id_index ON public.email_log_attachments USING btree (email_log_id);


--
-- Name: mail_reports_file_hash_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_reports_file_hash_index ON public.email_logs USING btree (file_hash);


--
-- Name: mail_reports_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_reports_message_id_index ON public.email_logs USING btree (message_id);


--
-- Name: mail_reports_thread_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mail_reports_thread_id_index ON public.email_logs USING btree (thread_id);


--
-- Name: matter_checklists_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX matter_checklists_matter_id_index ON public.matter_checklists USING btree (matter_id);


--
-- Name: matter_reminders_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX matter_reminders_client_matter_id_index ON public.matter_reminders USING btree (client_matter_id);


--
-- Name: matter_reminders_visa_matter_type_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX matter_reminders_visa_matter_type_idx ON public.matter_reminders USING btree (visa_type, client_matter_id, type);


--
-- Name: matter_reminders_visa_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX matter_reminders_visa_type_index ON public.matter_reminders USING btree (visa_type);


--
-- Name: message_attachments_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_attachments_message_id_index ON public.message_attachments USING btree (message_id);


--
-- Name: message_recipients_message_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_recipients_message_id_index ON public.message_recipients USING btree (message_id);


--
-- Name: message_recipients_message_id_recipient_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_recipients_message_id_recipient_id_index ON public.message_recipients USING btree (message_id, recipient_id);


--
-- Name: message_recipients_recipient_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_recipients_recipient_id_index ON public.message_recipients USING btree (recipient_id);


--
-- Name: message_recipients_recipient_id_is_read_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX message_recipients_recipient_id_is_read_index ON public.message_recipients USING btree (recipient_id, is_read);


--
-- Name: nomination_document_types_client_id_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nomination_document_types_client_id_client_matter_id_index ON public.nomination_document_types USING btree (client_id, client_matter_id);


--
-- Name: nomination_document_types_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX nomination_document_types_client_id_index ON public.nomination_document_types USING btree (client_id);


--
-- Name: notes_lead_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_lead_id_index ON public.notes USING btree (lead_id);


--
-- Name: notes_mail_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_mail_id_index ON public.notes USING btree (mail_id);


--
-- Name: notes_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_matter_id_index ON public.notes USING btree (matter_id);


--
-- Name: notes_unique_group_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_unique_group_id_index ON public.notes USING btree (unique_group_id);


--
-- Name: notes_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notes_user_id_index ON public.notes USING btree (user_id);


--
-- Name: notifications_module_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_module_id_index ON public.notifications USING btree (module_id);


--
-- Name: notifications_sender_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_sender_id_index ON public.notifications USING btree (sender_id);


--
-- Name: personal_document_types_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_document_types_client_id_index ON public.personal_document_types USING btree (client_id);


--
-- Name: phone_verifications_client_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX phone_verifications_client_contact_id_index ON public.phone_verifications USING btree (client_contact_id);


--
-- Name: phone_verifications_otp_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX phone_verifications_otp_code_index ON public.phone_verifications USING btree (otp_code);


--
-- Name: phone_verifications_otp_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX phone_verifications_otp_expires_at_index ON public.phone_verifications USING btree (otp_expires_at);


--
-- Name: phone_verifications_phone_country_code_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX phone_verifications_phone_country_code_index ON public.phone_verifications USING btree (phone, country_code);


--
-- Name: portal_document_checklists_doc_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_document_checklists_doc_type_index ON public.document_checklists USING btree (doc_type);


--
-- Name: portal_document_checklists_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX portal_document_checklists_status_index ON public.document_checklists USING btree (status);


--
-- Name: refresh_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX refresh_tokens_expires_at_index ON public.refresh_tokens USING btree (expires_at);


--
-- Name: refresh_tokens_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX refresh_tokens_token_index ON public.refresh_tokens USING btree (token);


--
-- Name: refresh_tokens_user_id_is_revoked_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX refresh_tokens_user_id_is_revoked_index ON public.refresh_tokens USING btree (user_id, is_revoked);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: signature_activities_action_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signature_activities_action_type_index ON public.signature_activities USING btree (action_type);


--
-- Name: signature_activities_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signature_activities_created_at_index ON public.signature_activities USING btree (created_at);


--
-- Name: signers_document_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX signers_document_id_index ON public.signers USING btree (document_id);


--
-- Name: sms_logs_client_contact_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_client_contact_id_index ON public.sms_logs USING btree (client_contact_id);


--
-- Name: sms_logs_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_client_id_index ON public.sms_logs USING btree (client_id);


--
-- Name: sms_logs_client_id_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_client_id_sent_at_index ON public.sms_logs USING btree (client_id, sent_at);


--
-- Name: sms_logs_message_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_message_type_index ON public.sms_logs USING btree (message_type);


--
-- Name: sms_logs_provider_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_provider_index ON public.sms_logs USING btree (provider);


--
-- Name: sms_logs_sender_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_sender_id_index ON public.sms_logs USING btree (sender_id);


--
-- Name: sms_logs_sent_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_sent_at_index ON public.sms_logs USING btree (sent_at);


--
-- Name: sms_logs_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_logs_status_index ON public.sms_logs USING btree (status);


--
-- Name: sms_templates_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_templates_category_index ON public.sms_templates USING btree (category);


--
-- Name: sms_templates_is_active_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_templates_is_active_category_index ON public.sms_templates USING btree (is_active, category);


--
-- Name: sms_templates_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sms_templates_is_active_index ON public.sms_templates USING btree (is_active);


--
-- Name: trust_accounting_periods_period_start_period_end_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_accounting_periods_period_start_period_end_index ON public.trust_accounting_periods USING btree (period_start, period_end);


--
-- Name: trust_audit_logs_performed_by_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_audit_logs_performed_by_index ON public.trust_audit_logs USING btree (performed_by);


--
-- Name: trust_audit_logs_table_name_row_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_audit_logs_table_name_row_id_index ON public.trust_audit_logs USING btree (table_name, row_id);


--
-- Name: trust_bank_statement_lines_trust_bank_account_id_value_date_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_bank_statement_lines_trust_bank_account_id_value_date_ind ON public.trust_bank_statement_lines USING btree (trust_bank_account_id, value_date);


--
-- Name: trust_monthly_archives_period_year_period_month_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_monthly_archives_period_year_period_month_index ON public.trust_monthly_archives USING btree (period_year, period_month);


--
-- Name: trust_withdrawal_authorities_client_id_invoice_no_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_withdrawal_authorities_client_id_invoice_no_index ON public.trust_withdrawal_authorities USING btree (client_id, invoice_no);


--
-- Name: visa_document_types_client_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX visa_document_types_client_id_index ON public.visa_document_types USING btree (client_id);


--
-- Name: visa_document_types_client_matter_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX visa_document_types_client_matter_id_index ON public.visa_document_types USING btree (client_matter_id);


--
-- Name: admins admins_archived_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_archived_by_foreign FOREIGN KEY (archived_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: booking_appointments booking_appointments_assigned_by_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_assigned_by_admin_id_foreign FOREIGN KEY (assigned_by_admin_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: booking_appointments booking_appointments_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: booking_appointments booking_appointments_consultant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_consultant_id_foreign FOREIGN KEY (consultant_id) REFERENCES public.appointment_consultants(id) ON DELETE SET NULL;


--
-- Name: client_access_grants client_access_grants_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: client_access_grants client_access_grants_approved_by_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_approved_by_staff_id_foreign FOREIGN KEY (approved_by_staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: client_access_grants client_access_grants_office_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_office_id_foreign FOREIGN KEY (office_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: client_access_grants client_access_grants_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;


--
-- Name: client_access_grants client_access_grants_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE SET NULL;


--
-- Name: client_art_references client_art_references_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: client_art_references client_art_references_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: client_art_references client_art_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: client_art_references client_art_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: client_contacts client_contacts_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_contacts
    ADD CONSTRAINT client_contacts_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: client_emails client_emails_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: client_matter_opposing_parties client_matter_opposing_parties_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_opposing_parties
    ADD CONSTRAINT client_matter_opposing_parties_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: client_matter_payment_forms_verifications client_matter_payment_forms_verifications_client_matter_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications
    ADD CONSTRAINT client_matter_payment_forms_verifications_client_matter_id_fore FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: client_matter_references client_matter_references_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: client_matter_references client_matter_references_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: client_matter_references client_matter_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: client_matter_references client_matter_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: client_matter_tasks client_matter_tasks_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_tasks
    ADD CONSTRAINT client_matter_tasks_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: client_matter_tasks client_matter_tasks_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_tasks
    ADD CONSTRAINT client_matter_tasks_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: client_matter_tasks client_matter_tasks_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_matter_tasks
    ADD CONSTRAINT client_matter_tasks_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: client_spouse_details client_spouse_details_related_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.client_spouse_details
    ADD CONSTRAINT client_spouse_details_related_client_id_foreign FOREIGN KEY (related_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: companies companies_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: companies companies_contact_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_contact_person_id_foreign FOREIGN KEY (contact_person_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: company_directors company_directors_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_directors company_directors_director_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_director_client_id_foreign FOREIGN KEY (director_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: company_nominations company_nominations_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_nominations company_nominations_nominated_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_nominated_client_id_foreign FOREIGN KEY (nominated_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- Name: company_sponsorships company_sponsorships_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_sponsorships
    ADD CONSTRAINT company_sponsorships_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: company_trading_names company_trading_names_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.company_trading_names
    ADD CONSTRAINT company_trading_names_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- Name: device_tokens device_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: disbursement_lines disbursement_lines_cost_assignment_form_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.disbursement_lines
    ADD CONSTRAINT disbursement_lines_cost_assignment_form_id_foreign FOREIGN KEY (cost_assignment_form_id) REFERENCES public.cost_assignment_forms(id) ON DELETE CASCADE;


--
-- Name: email_templates email_templates_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_matter_id_foreign FOREIGN KEY (matter_id) REFERENCES public.matters(id) ON DELETE CASCADE;


--
-- Name: lead_matter_references lead_matter_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: lead_matter_references lead_matter_references_lead_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: lead_matter_references lead_matter_references_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_matter_id_foreign FOREIGN KEY (matter_id) REFERENCES public.matters(id) ON DELETE CASCADE;


--
-- Name: lead_matter_references lead_matter_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: lead_reminders lead_reminders_lead_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: lead_reminders lead_reminders_reminded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_reminded_by_foreign FOREIGN KEY (reminded_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: matter_reminders matter_reminders_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- Name: matter_reminders matter_reminders_reminded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_reminded_by_foreign FOREIGN KEY (reminded_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: message_attachments message_attachments_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: message_recipients message_recipients_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.message_recipients
    ADD CONSTRAINT message_recipients_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- Name: refresh_tokens refresh_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- Name: signature_activities signature_activities_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_activities
    ADD CONSTRAINT signature_activities_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: signature_activities signature_activities_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_activities
    ADD CONSTRAINT signature_activities_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: signature_fields signature_fields_document_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_fields
    ADD CONSTRAINT signature_fields_document_id_foreign FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE;


--
-- Name: signature_fields signature_fields_signer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.signature_fields
    ADD CONSTRAINT signature_fields_signer_id_foreign FOREIGN KEY (signer_id) REFERENCES public.signers(id) ON DELETE SET NULL;


--
-- Name: staff staff_office_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_office_id_foreign FOREIGN KEY (office_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- Name: staff staff_role_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_role_foreign FOREIGN KEY (role) REFERENCES public.user_roles(id) ON DELETE SET NULL;


--
-- Name: trust_bank_statement_lines trust_bank_statement_lines_matched_account_client_receipt_id_fo; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines
    ADD CONSTRAINT trust_bank_statement_lines_matched_account_client_receipt_id_fo FOREIGN KEY (matched_account_client_receipt_id) REFERENCES public.account_client_receipts(id) ON DELETE SET NULL;


--
-- Name: trust_bank_statement_lines trust_bank_statement_lines_matched_by_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines
    ADD CONSTRAINT trust_bank_statement_lines_matched_by_staff_id_foreign FOREIGN KEY (matched_by_staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- Name: trust_bank_statement_lines trust_bank_statement_lines_trust_bank_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_bank_statement_lines
    ADD CONSTRAINT trust_bank_statement_lines_trust_bank_account_id_foreign FOREIGN KEY (trust_bank_account_id) REFERENCES public.trust_bank_accounts(id) ON DELETE CASCADE;


--
-- Name: trust_withdrawal_authorities trust_withdrawal_authorities_account_client_receipt_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities
    ADD CONSTRAINT trust_withdrawal_authorities_account_client_receipt_id_foreign FOREIGN KEY (account_client_receipt_id) REFERENCES public.account_client_receipts(id) ON DELETE CASCADE;


--
-- Name: trust_withdrawal_authorities trust_withdrawal_authorities_authorised_by_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities
    ADD CONSTRAINT trust_withdrawal_authorities_authorised_by_staff_id_foreign FOREIGN KEY (authorised_by_staff_id) REFERENCES public.staff(id) ON DELETE RESTRICT;


--
-- Name: trust_withdrawal_authorities trust_withdrawal_authorities_authority_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_withdrawal_authorities
    ADD CONSTRAINT trust_withdrawal_authorities_authority_type_id_foreign FOREIGN KEY (authority_type_id) REFERENCES public.trust_withdrawal_authority_types(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict WLhNTcZejcsHACHN92RXegMrf72SFitmPxU6E2X72OgjhH7RRh19EwP9KXTMieD

--
-- PostgreSQL database dump
--

\restrict hUY07OBj61Grq4n1GTlEqGdckketfD29ESGmv2kHrvOVTsHuPNri2G9m8x3p5iQ

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
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
1	0001_01_01_000000_create_admins_table	1
2	0001_01_01_000000_create_users_table	1
3	0001_01_01_000001_create_cache_table	1
4	0001_01_01_000002_create_jobs_table	1
5	2024_01_01_000000_create_client_passport_informations_table	1
6	2024_10_20_000001_create_appointment_consultants_table	1
7	2024_10_20_000002_create_booking_appointments_table	1
8	2024_10_20_000003_create_appointment_sync_logs_table	1
9	2025_01_15_000000_update_defacto_to_de_facto_in_admins_table	1
10	2025_02_07_000000_remove_emergency_contact_columns_from_admins_table	1
11	2025_02_22_000001_rename_document_notes_to_signature_activities	1
12	2025_09_01_000000_create_legacy_stub_tables_for_fresh_installs	1
13	2025_09_11_225540_create_device_tokens_table	1
14	2025_09_11_230000_create_refresh_tokens_table	1
15	2025_10_04_171643_add_passport_country_to_client_passport_informations_table	1
16	2025_10_04_191900_create_client_contacts_table	1
17	2025_10_04_192020_create_phone_verifications_table	1
18	2025_10_04_192042_add_verification_columns_to_client_contacts_table	1
19	2025_10_04_202009_remove_manual_email_verification_fields_from_admins_table	1
20	2025_10_04_202010_create_client_emails_table	1
21	2025_10_04_202014_add_verification_fields_to_client_emails_table	1
22	2025_10_04_202019_create_email_verifications_table	1
23	2025_10_06_172952_add_structured_address_fields_to_client_addresses_table	1
24	2025_10_07_000000_create_message_recipients_table	1
25	2025_10_08_100000_create_anzsco_occupations_table	1
26	2025_10_08_212653_add_anzsco_occupation_id_to_client_occupations_table	1
27	2025_10_09_000000_update_assessment_validity_periods	1
28	2025_10_10_171531_drop_seo_pages_table	1
29	2025_10_12_185509_add_eoi_roi_workflow_columns_to_client_eoi_references_table	1
30	2025_10_12_223206_add_points_calculation_fields_to_client_qualifications_table	1
31	2025_10_12_223221_add_citizenship_fields_to_client_spouse_details_table	1
32	2025_10_12_223235_add_fte_multiplier_to_client_experiences_table	1
33	2025_10_13_133601_add_eoi_qualification_fields_to_admins_table	1
34	2025_10_13_161639_rename_martial_status_to_marital_status_in_admins_table	1
35	2025_10_13_190305_add_proficiency_level_to_client_testscore_table	1
36	2025_10_13_200434_add_related_client_id_to_client_spouse_details_table	1
37	2025_10_14_201641_create_sms_logs_table	1
38	2025_10_14_201706_create_sms_templates_table	1
39	2025_10_14_201735_add_sms_fields_to_activities_logs_table	1
40	2025_10_15_183908_add_description_to_sms_templates_table	1
41	2025_10_20_191713_add_signature_dashboard_fields_to_documents_table	1
42	2025_10_21_175052_add_tagname_column_to_admins_table	1
43	2025_10_21_190455_create_document_notes_table	1
44	2025_10_21_200000_add_smtp_fields_to_emails_table	1
45	2025_10_21_225122_add_signed_hash_to_documents_table	1
46	2025_10_22_194148_add_email_fields_to_signers_table	1
47	2025_10_24_152207_add_composite_indexes_to_notes_table	1
48	2025_10_25_164851_create_email_labels_table	1
49	2025_10_25_172232_add_python_analysis_to_mail_reports	1
50	2025_10_25_172234_create_email_label_mail_report_pivot	1
51	2025_10_25_172236_seed_default_email_labels	1
52	2025_10_25_190321_remove_category_priority_from_mail_reports	1
53	2025_10_25_192936_create_mail_report_attachments_table	1
54	2025_10_31_163256_add_void_fee_transfer_columns_to_account_client_receipts_table	1
55	2025_11_01_000000_add_pdf_document_id_to_account_client_receipts	1
56	2025_11_06_210000_fix_client_occupations_foreign_key	1
57	2025_11_09_044318_update_booking_appointments_client_fk	1
58	2025_11_22_005215_create_clientportal_details_audit_table	1
59	2025_12_05_011353_add_paid_status_to_booking_appointments_table	1
60	2025_12_12_000000_add_client_counter_and_unique_constraint_to_admins	1
61	2025_12_14_155025_fix_user_logs_id_sequence	1
62	2025_12_14_155107_fix_migrations_id_sequence	1
63	2025_12_14_155355_fix_admins_id_sequence	1
64	2025_12_15_000000_fix_and_migrate_client_visa_countries	1
65	2025_12_15_000001_fix_and_migrate_client_addresses	1
66	2025_12_15_000002_fix_and_migrate_client_travel_informations	1
67	2025_12_16_174047_add_indexes_to_notifications_table	1
68	2025_12_17_145310_add_office_to_client_matters_and_documents	1
69	2025_12_17_171644_add_agent_fields_to_admins_table	1
70	2025_12_17_185802_fix_client_contacts_verified_by_foreign_key	1
71	2025_12_23_175551_drop_unused_tables	1
72	2025_12_23_180714_drop_additional_unused_tables	1
73	2025_12_24_000000_add_tag_type_to_tags_table	1
74	2025_12_24_000000_drop_old_appointment_system_tables	1
75	2025_12_24_000001_drop_unused_legacy_tables	1
76	2025_12_25_182500_fix_activities_logs_id_sequence	1
77	2025_12_25_201151_fix_admins_table_primary_key_and_duplicate_id	1
78	2025_12_26_000000_fix_client_testscore_primary_key_and_duplicate_ids	1
79	2025_12_26_000001_fix_all_tables_primary_keys_and_duplicate_ids	1
80	2025_12_26_212544_fix_documents_table_primary_key_and_duplicate_ids	1
81	2025_12_27_004110_add_default_values_to_appointment_sync_logs_table	1
82	2025_12_27_005121_add_default_values_to_admins_and_booking_appointments_tables	1
83	2026_01_10_171719_add_cancelled_at_to_signers_table	1
84	2026_01_10_175646_increase_signers_status_column_length	1
85	2026_01_15_162412_add_ajay_to_calendar_type_enum	1
86	2026_01_22_004540_create_user_logs_table	1
87	2026_01_26_000000_add_archived_by_to_admins_table	1
88	2026_01_26_174555_add_company_fields_to_admins_table	1
89	2026_01_26_174557_add_is_for_company_to_matters_table	1
90	2026_01_26_175322_create_companies_table	1
91	2026_01_28_100000_create_appointment_payments_table	1
92	2026_01_29_000000_add_confirmation_workflow_to_client_eoi_references	1
93	2026_01_30_000000_create_client_art_references_table	1
94	2026_01_31_000000_add_source_to_activities_logs_table	1
95	2026_02_06_000000_drop_is_archived_from_checkin_logs_table	1
96	2026_02_07_000000_widen_activity_type_in_activities_logs_table	1
97	2026_02_07_192647_drop_start_process_from_admins_table	1
98	2026_02_09_000000_drop_visa_country_from_client_visa_countries	1
99	2026_02_11_000000_drop_safe_to_delete_columns_from_admins_table	1
100	2026_02_11_000001_drop_gst_and_fax_columns_from_admins_table	1
101	2026_02_11_000002_drop_att_contact_columns_from_admins_table	1
102	2026_02_11_000003_drop_bansal_lead_columns_from_admins_table	1
103	2026_02_11_000004_drop_phase4_legacy_columns_from_admins_table	1
104	2026_02_14_000000_create_staff_table	1
105	2026_02_14_000001_copy_staff_from_admins_to_staff	1
106	2026_02_14_000002_drop_staff_columns_from_admins_table	1
107	2026_02_14_100000_drop_profile_img_telephone_eoi_columns	1
108	2026_02_14_110000_drop_unused_columns_from_staff_table	1
109	2026_02_16_000001_create_client_tr_references_table	1
110	2026_02_16_000002_add_tr_checklist_status_to_client_matters	1
111	2026_02_16_000003_create_tr_matter_reminders_table	1
112	2026_02_16_000004_create_client_visitor_references_table	1
113	2026_02_16_000005_add_visitor_checklist_status_to_client_matters	1
114	2026_02_16_000006_create_visitor_matter_reminders_table	1
115	2026_02_16_000007_create_client_student_references_table	1
116	2026_02_16_000008_add_student_checklist_status_to_client_matters	1
117	2026_02_16_000009_create_student_matter_reminders_table	1
118	2026_02_16_000010_create_client_pr_references_table	1
119	2026_02_16_000011_add_pr_checklist_status_to_client_matters	1
120	2026_02_16_000012_create_pr_matter_reminders_table	1
121	2026_02_16_000013_create_client_employer_sponsored_references_table	1
122	2026_02_16_000014_add_employer_sponsored_checklist_status_to_client_matters	1
123	2026_02_16_000015_create_employer_sponsored_matter_reminders_table	1
124	2026_02_16_100000_add_is_pinned_to_client_reference_tables	1
125	2026_02_17_000000_add_client_application_sent_to_account_client_receipts	1
126	2026_02_17_000000_add_sort_order_and_reorder_workflow_stages	1
127	2026_02_17_000001_remove_initial_consultation_and_payment_stages_from_workflow	1
128	2026_02_17_100000_add_deadline_to_client_matters	1
129	2026_02_17_120000_rename_payment_verified_and_add_verification_table	1
130	2026_02_17_140000_add_decision_outcome_note_to_client_matters	1
131	2026_02_18_000000_create_message_attachments_table	1
132	2026_02_18_100000_add_is_pinned_to_client_eoi_references	1
133	2026_02_18_100000_add_per_matter_workflows	1
134	2026_02_18_201513_add_form956_id_to_documents_table	1
135	2026_02_20_100000_add_anzsco_occupation_id_to_client_eoi_references	1
136	2026_02_20_100000_create_lead_visa_checklist_references_tables	1
137	2026_02_20_150000_drop_unused_documents_columns	1
138	2026_02_20_160000_drop_polymorphic_and_signature_dashboard_columns_from_documents	1
139	2026_02_21_000000_drop_agents_table	1
140	2026_02_22_000000_drop_clients_table	1
141	2026_02_22_000001_drop_follow_up_columns_from_booking_appointments	1
142	2026_02_22_100000_drop_unused_columns_from_client_art_references	1
143	2026_02_22_110000_rename_folloup_and_followup_date_in_notes_table	1
144	2026_02_22_120000_drop_points_columns_from_client_qualifications_table	1
145	2026_02_22_130000_drop_city_from_client_addresses_table	1
146	2026_02_22_140000_drop_representing_partners_and_applications_partner_id	1
147	2026_02_23_000001_drop_settings_table	1
148	2026_02_23_000002_rename_checklist_tables	1
149	2026_02_23_200000_create_client_matter_references_and_migrate	1
150	2026_02_24_000000_create_matter_reminders_and_migrate	1
151	2026_02_24_000000_rename_mail_reports_to_email_logs	1
152	2026_02_25_000000_create_lead_matter_references_and_migrate	1
153	2026_02_26_000000_create_lead_reminders_and_migrate	1
154	2026_02_26_000001_add_kunal_calendar	1
155	2026_02_27_000000_create_email_templates_and_migrate	1
156	2026_02_27_000001_add_partner_checklist_status_to_client_matters	1
157	2026_02_27_000002_add_parents_checklist_status_to_client_matters	1
158	2026_02_27_011605_drop_make_mandatory_date_time_from_cp_doc_checklist	1
159	2026_02_27_012246_reorder_client_matter_id_after_client_id_in_cp_doc_checklist	1
160	2026_02_27_013123_rename_document_type_to_cp_checklist_name_in_cp_doc_checklist	1
161	2026_02_27_120000_rename_client_application_sent_to_client_portal_sent	1
162	2026_02_27_135225_rename_user_logs_to_staff_login_logs	1
163	2026_02_27_140000_update_activities_logs_use_for_application_to_matter	1
164	2026_02_28_000000_add_alias_to_email_templates	1
165	2026_02_28_100000_create_company_trading_names_table	1
166	2026_02_28_100000_drop_applications_super_sub_agent_columns	1
167	2026_02_28_100001_add_employer_sponsorship_fields_to_companies_table	1
168	2026_02_28_100002_create_company_directors_table	1
169	2026_02_28_100003_create_company_nominations_table	1
170	2026_02_28_110000_drop_applications_table	1
171	2026_02_28_120000_migrate_application_documents_to_documents	1
172	2026_02_28_120000_remove_client_role_from_user_roles	1
173	2026_02_28_130000_rename_application_document_lists_to_cp_doc_checklist	1
174	2026_02_28_140000_rename_cp_doc_checklist_to_cp_doc_checklists	1
175	2026_02_28_150000_rename_typename_and_type_in_cp_doc_checklists	1
176	2026_02_28_160000_reorder_columns_in_cp_doc_checklists	1
177	2026_02_28_170000_add_director_client_id_to_company_directors_table	1
178	2026_03_07_000000_fix_client_eoi_references_checked_by_fk_to_staff	1
179	2026_03_09_000000_add_client_portal_payment_columns_to_account_client_receipts	1
180	2026_03_13_000000_drop_smtp_and_password_from_emails_table	1
181	2026_03_18_000000_change_client_addresses_zip_to_string	1
182	2026_03_21_100000_add_sheet_access_to_staff_table	1
183	2026_03_21_120000_add_eftpos_surcharge_amount_to_account_client_receipts	1
184	2026_03_24_120000_add_quick_access_enabled_to_staff_table	1
185	2026_03_24_120001_create_client_access_grants_table	1
186	2026_03_27_000001_create_nomination_document_types_table	1
187	2026_03_27_100000_create_company_sponsorships_table	1
188	2026_03_27_120000_add_extended_workforce_fields_to_companies_table	1
189	2026_03_27_130000_trustee_company_type_and_widen_trust_abn	1
190	2026_03_31_120000_add_automated_sms_templates	1
191	2026_03_31_200000_ensure_lead_pipeline_columns_on_admins_table	1
192	2026_03_31_201000_add_lead_id_to_documents_table	1
193	2026_04_01_100000_create_front_desk_check_ins_table	1
194	2026_04_01_120000_add_google_review_reminder_columns_to_admins_table	1
195	2026_04_01_120000_add_walk_in_contact_to_checkin_logs_table	1
196	2026_04_01_220000_fix_sms_templates_column_defaults	1
197	2026_04_02_000000_add_grant_super_admin_access_to_staff_table	1
198	2026_04_03_120000_drop_client_eoi_references_table	1
199	2026_04_03_140000_update_sms_templates_bansal_lawyers_brand	1
200	2026_04_04_000001_remove_anzsco_occupation_system	1
201	2026_04_04_120000_add_status_to_documents_table	1
202	2026_04_04_130000_add_is_deleted_to_admins_table	1
203	2026_04_04_140000_add_office_visit_columns_to_checkin_logs_table	1
204	2026_04_04_150000_add_missing_note_columns_to_notes_table	1
205	2026_04_04_000000_add_core_columns_to_account_client_receipts_table	2
206	2026_04_04_100000_fix_account_client_receipts_trans_dates_to_varchar	3
207	2026_04_04_160000_add_legacy_client_matters_columns_for_postgresql	4
208	2026_04_04_200000_create_countries_table_if_missing	4
209	2026_04_04_210000_add_archive_columns_to_admins_if_missing	4
210	2026_04_04_220000_ensure_archive_columns_on_admins	4
211	2026_04_04_230000_add_core_columns_to_matters_if_missing	4
212	2026_04_04_240000_add_core_columns_to_branches_if_missing	4
213	2026_04_08_120000_add_type_to_admins_if_missing	4
214	2026_04_08_140000_create_client_travel_informations_table	4
215	2026_04_08_150000_create_client_characters_table	4
216	2026_04_08_160000_create_client_relationships_table	4
217	2026_04_08_210000_seed_bansal_law_practice_matter_types	4
218	2026_04_08_220000_add_refer_by_to_admins_table	4
219	2026_04_08_230000_seed_dummy_legal_practitioner_shubam_bansal	4
220	2026_04_09_150000_add_user_id_to_client_matters_if_missing	4
221	2026_04_09_160000_add_reference_columns_to_client_matters_if_missing	4
222	2026_04_09_170000_add_legacy_client_addresses_columns_if_missing	4
223	2026_04_09_180000_add_legacy_client_visa_countries_columns_if_missing	4
224	2026_04_09_180500_ensure_dob_age_gender_on_admins_table	4
225	2026_04_09_190000_add_legacy_client_occupations_columns_if_missing	4
226	2026_04_09_200000_add_legacy_client_testscore_columns_if_missing	4
227	2026_04_09_210000_add_legacy_client_qualifications_columns_if_missing	4
228	2026_04_09_220000_add_legacy_client_experiences_columns_if_missing	4
229	2026_04_09_230000_add_case_detail_to_client_matters_if_missing	4
230	2026_04_09_235000_rename_kunal_calendar_consultant_name_to_michael	4
231	2026_04_10_120000_add_incidence_fields_to_client_matters_if_missing	4
232	2026_04_10_140000_create_personal_document_types_if_missing	4
233	2026_04_10_160000_add_core_documents_columns_if_missing	4
234	2026_04_10_170000_create_visa_document_types_if_missing	4
235	2026_04_10_175000_ensure_user_roles_display_columns	4
236	2026_04_10_180000_rename_migration_agent_user_role_to_solicitor	4
237	2026_04_11_100000_create_cost_assignment_forms_if_missing	4
238	2026_04_11_130000_create_portal_document_checklists_if_missing	4
239	2026_04_11_140000_create_agent_details_if_missing	4
240	2026_04_11_150000_create_matter_checklists_if_missing	4
241	2026_04_11_160000_seed_melbourne_india_branches	5
242	2026_04_11_170000_seed_default_teams_for_staff_departments	6
243	2026_04_11_180000_seed_canonical_user_roles_from_config	7
244	2026_04_10_130000_ensure_activities_logs_crm_columns	8
245	2026_04_10_140000_ensure_dob_verification_columns_on_admins_table	8
246	2026_04_12_120000_rename_client_matters_sel_migration_agent_to_sel_legal_practitioner	8
247	2026_04_12_120001_rename_staff_is_migration_agent_to_is_solicitor	8
248	2026_04_12_120002_reconcile_form956_is_registered_to_is_legal_practitioner	8
249	2026_04_12_120003_rename_admins_is_migration_agent_to_is_solicitor_when_present	8
250	2026_04_12_120004_backfill_empty_user_role_names	9
251	2026_04_10_210000_add_missing_columns_to_notifications_table_if_missing	10
252	2026_04_11_100000_add_email_type_to_admins_table	10
253	2026_04_11_100000_ensure_document_id_on_signers_table	10
254	2026_04_11_100000_ensure_notifications_columns_pgsql_if_missing	10
255	2026_04_11_101000_add_contact_type_to_admins_table	10
256	2026_04_11_110000_ensure_signers_document_id_retry	10
257	2026_04_11_140000_ensure_signed_at_on_signers_table	10
258	2026_04_11_200000_create_client_court_hearings_if_missing	10
259	2026_04_11_210000_create_client_legal_forms_table	10
260	2026_04_12_100000_create_checkin_history_table_if_missing	10
261	2026_04_12_130000_replace_dept_fee_with_disbursements	10
262	2026_04_12_160000_law_practice_crm_cleanup	10
263	2026_04_13_100000_ensure_phone_country_code_on_admins_if_missing	10
264	2026_04_13_110000_ensure_user_id_on_admins_if_missing	10
265	2026_04_14_100000_ensure_signers_recipient_columns_if_missing	10
266	2026_04_14_110000_drop_client_portal_columns	10
267	2026_04_13_000000_create_account_all_invoice_receipts_table	11
268	2026_04_13_000001_create_signature_fields_table	11
269	2026_04_13_000002_create_signature_activities_table	11
270	2026_04_13_000003_add_outbound_columns_to_emails_table	11
271	2026_04_15_120000_client_portal_removal_data_cleanup	11
272	2026_04_14_120000_drop_form956_feature	12
273	2026_04_16_120000_add_user_id_to_booking_appointments_if_missing	13
274	2026_04_16_120000_drop_department_and_other_reference_from_client_matters	13
275	2026_04_16_140000_add_website_status_code_to_booking_appointments_if_missing	13
276	2026_04_18_000000_migrate_client_tags_to_json_and_drop_tags_table	14
277	2026_04_18_100000_add_matter_stream_and_legal_party_fields	14
278	2026_04_18_120000_create_client_matter_tasks_table	14
279	2026_04_17_000000_ensure_tagname_on_admins_table	15
280	2026_04_22_000000_ensure_email_logs_core_columns_if_missing	15
281	2026_04_22_120000_ensure_source_on_admins_table	15
282	2026_04_30_000000_fix_booking_appointments_assigned_by_fk_to_staff	16
283	2026_04_30_150000_remove_booking_appointments_for_emails_on_or_before_2026_04_30	16
284	2026_04_30_160000_rename_gn_client_unique_matter_no_to_matter_prefix	16
285	2026_05_01_120000_convert_leads_with_assigned_matters_to_clients	16
286	2026_05_11_140000_backfill_client_matters_default_assignees	16
287	2026_05_17_120000_trust_compliance_phase1	16
288	2026_05_17_130000_trust_sequence_type	16
289	2026_05_17_140000_trust_compliance_phase2_period_unlock	16
290	2026_05_17_150000_trust_bank_reconciliation_phase4	16
291	2026_05_17_170000_trust_rule42_withdrawal_authority	16
292	2026_05_20_100000_trust_compliance_phase6_gaps	17
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 292, true);


--
-- PostgreSQL database dump complete
--

\unrestrict hUY07OBj61Grq4n1GTlEqGdckketfD29ESGmv2kHrvOVTsHuPNri2G9m8x3p5iQ

