--
-- PostgreSQL database dump
--

\restrict iDcV0NIoSjKk5PdGPKGtqAcVsOdBMMwbcNeR6BehGabJ8JZszroAzZGzvkmFMRL

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2026-04-03 17:05:07

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
-- TOC entry 277 (class 1259 OID 887981)
-- Name: account_client_receipts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.account_client_receipts (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    void_fee_transfer smallint DEFAULT '0'::smallint,
    voided_at timestamp(0) without time zone,
    voided_by bigint,
    pdf_document_id bigint,
    client_portal_sent smallint DEFAULT '0'::smallint CONSTRAINT account_client_receipts_client_application_sent_not_null NOT NULL,
    client_portal_sent_at timestamp(0) without time zone,
    client_portal_payment_token character varying(500),
    client_portal_payment_type character varying(50),
    eftpos_surcharge_amount numeric(10,2)
);


ALTER TABLE public.account_client_receipts OWNER TO postgres;

--
-- TOC entry 6059 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN account_client_receipts.client_portal_payment_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.account_client_receipts.client_portal_payment_type IS 'google_pay, apple_pay, or stripe';


--
-- TOC entry 276 (class 1259 OID 887980)
-- Name: account_client_receipts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.account_client_receipts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.account_client_receipts_id_seq OWNER TO postgres;

--
-- TOC entry 6060 (class 0 OID 0)
-- Dependencies: 276
-- Name: account_client_receipts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.account_client_receipts_id_seq OWNED BY public.account_client_receipts.id;


--
-- TOC entry 249 (class 1259 OID 887861)
-- Name: activities_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.activities_logs (
    id bigint NOT NULL,
    client_id bigint,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sms_log_id bigint,
    activity_type character varying(64) DEFAULT 'note'::character varying NOT NULL,
    source character varying(50)
);


ALTER TABLE public.activities_logs OWNER TO postgres;

--
-- TOC entry 6061 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN activities_logs.sms_log_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.activities_logs.sms_log_id IS 'Reference to SMS log if activity is SMS-related';


--
-- TOC entry 6062 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN activities_logs.activity_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.activities_logs.activity_type IS 'Type: note, document, sms, email, etc.';


--
-- TOC entry 6063 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN activities_logs.source; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.activities_logs.source IS 'Origin: client_portal, crm, etc. NULL = legacy/unset.';


--
-- TOC entry 248 (class 1259 OID 887860)
-- Name: activities_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.activities_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.activities_logs_id_seq OWNER TO postgres;

--
-- TOC entry 6064 (class 0 OID 0)
-- Dependencies: 248
-- Name: activities_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.activities_logs_id_seq OWNED BY public.activities_logs.id;


--
-- TOC entry 222 (class 1259 OID 887574)
-- Name: admins; Type: TABLE; Schema: public; Owner: postgres
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
    cp_status smallint DEFAULT '0'::smallint NOT NULL,
    cp_random_code character varying(191),
    cp_code_verify smallint DEFAULT '0'::smallint NOT NULL,
    cp_token_generated_at timestamp(0) without time zone,
    visa_expiry_verified_at timestamp(0) without time zone,
    visa_expiry_verified_by integer,
    naati_test character varying(191),
    py_test character varying(191),
    naati_date date,
    py_date date,
    marital_status character varying(191),
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
    google_review_reminder_snooze_until timestamp(0) without time zone
);


ALTER TABLE public.admins OWNER TO postgres;

--
-- TOC entry 6065 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.australian_study; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.australian_study IS 'Has Australian study requirement (2+ years)';


--
-- TOC entry 6066 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.australian_study_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.australian_study_date IS 'Australian study completion date';


--
-- TOC entry 6067 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.specialist_education; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.specialist_education IS 'Has specialist education qualification (STEM)';


--
-- TOC entry 6068 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.specialist_education_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.specialist_education_date IS 'Specialist education completion date';


--
-- TOC entry 6069 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.regional_study; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.regional_study IS 'Has regional study (studied in regional Australia)';


--
-- TOC entry 6070 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.regional_study_date; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.regional_study_date IS 'Regional study completion date';


--
-- TOC entry 6071 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.archived_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.archived_by IS 'ID of the admin who archived the client';


--
-- TOC entry 6072 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN admins.is_company; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.admins.is_company IS 'Flag to indicate if this is a company lead/client. Company data is stored in companies table.';


--
-- TOC entry 221 (class 1259 OID 887573)
-- Name: admins_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admins_id_seq OWNER TO postgres;

--
-- TOC entry 6073 (class 0 OID 0)
-- Dependencies: 221
-- Name: admins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admins_id_seq OWNED BY public.admins.id;


--
-- TOC entry 305 (class 1259 OID 888195)
-- Name: anzsco_occupations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.anzsco_occupations (
    id bigint NOT NULL,
    anzsco_code character varying(10) NOT NULL,
    occupation_title character varying(191) NOT NULL,
    occupation_title_normalized character varying(191),
    skill_level smallint,
    is_on_mltssl boolean DEFAULT false NOT NULL,
    is_on_stsol boolean DEFAULT false NOT NULL,
    is_on_rol boolean DEFAULT false NOT NULL,
    is_on_csol boolean DEFAULT false NOT NULL,
    assessing_authority character varying(191),
    assessment_validity_years integer DEFAULT 3 NOT NULL,
    additional_info text,
    alternate_titles text,
    is_active boolean DEFAULT true NOT NULL,
    created_by bigint,
    updated_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.anzsco_occupations OWNER TO postgres;

--
-- TOC entry 6074 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.anzsco_code; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.anzsco_code IS '6-digit ANZSCO code';


--
-- TOC entry 6075 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.occupation_title; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.occupation_title IS 'Official occupation title';


--
-- TOC entry 6076 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.occupation_title_normalized; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.occupation_title_normalized IS 'Lowercase normalized title for searching';


--
-- TOC entry 6077 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.skill_level; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.skill_level IS 'ANZSCO skill level 1-5';


--
-- TOC entry 6078 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.is_on_mltssl; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.is_on_mltssl IS 'Medium and Long-term Strategic Skills List';


--
-- TOC entry 6079 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.is_on_stsol; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.is_on_stsol IS 'Short-term Skilled Occupation List';


--
-- TOC entry 6080 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.is_on_rol; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.is_on_rol IS 'Regional Occupation List';


--
-- TOC entry 6081 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.is_on_csol; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.is_on_csol IS 'Consolidated Sponsored Occupation List (legacy)';


--
-- TOC entry 6082 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.assessing_authority; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.assessing_authority IS 'e.g., ACS, VETASSESS, TRA';


--
-- TOC entry 6083 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.assessment_validity_years; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.assessment_validity_years IS 'Years the assessment is valid';


--
-- TOC entry 6084 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.additional_info; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.additional_info IS 'Extra notes, requirements, or conditions';


--
-- TOC entry 6085 (class 0 OID 0)
-- Dependencies: 305
-- Name: COLUMN anzsco_occupations.alternate_titles; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.anzsco_occupations.alternate_titles IS 'Other common names for this occupation';


--
-- TOC entry 304 (class 1259 OID 888194)
-- Name: anzsco_occupations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.anzsco_occupations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.anzsco_occupations_id_seq OWNER TO postgres;

--
-- TOC entry 6086 (class 0 OID 0)
-- Dependencies: 304
-- Name: anzsco_occupations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.anzsco_occupations_id_seq OWNED BY public.anzsco_occupations.id;


--
-- TOC entry 289 (class 1259 OID 888029)
-- Name: cp_doc_checklists; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cp_doc_checklists (
    id bigint CONSTRAINT application_document_lists_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    wf_stage character varying(255),
    wf_stage_id bigint
);


ALTER TABLE public.cp_doc_checklists OWNER TO postgres;

--
-- TOC entry 288 (class 1259 OID 888028)
-- Name: application_document_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.application_document_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.application_document_lists_id_seq OWNER TO postgres;

--
-- TOC entry 6087 (class 0 OID 0)
-- Dependencies: 288
-- Name: application_document_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.application_document_lists_id_seq OWNED BY public.cp_doc_checklists.id;


--
-- TOC entry 235 (class 1259 OID 887690)
-- Name: appointment_consultants; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.appointment_consultants OWNER TO postgres;

--
-- TOC entry 6088 (class 0 OID 0)
-- Dependencies: 235
-- Name: COLUMN appointment_consultants.specializations; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_consultants.specializations IS 'Array of noe_ids this consultant handles';


--
-- TOC entry 234 (class 1259 OID 887689)
-- Name: appointment_consultants_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.appointment_consultants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.appointment_consultants_id_seq OWNER TO postgres;

--
-- TOC entry 6089 (class 0 OID 0)
-- Dependencies: 234
-- Name: appointment_consultants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.appointment_consultants_id_seq OWNED BY public.appointment_consultants.id;


--
-- TOC entry 325 (class 1259 OID 888536)
-- Name: appointment_payments; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.appointment_payments OWNER TO postgres;

--
-- TOC entry 6090 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.appointment_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.appointment_id IS 'FK to booking_appointments.id';


--
-- TOC entry 6091 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.transaction_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.transaction_id IS 'Stripe PaymentIntent ID (pi_xxx)';


--
-- TOC entry 6092 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.charge_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.charge_id IS 'Stripe Charge ID (ch_xxx)';


--
-- TOC entry 6093 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.customer_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.customer_id IS 'Stripe Customer ID (cus_xxx)';


--
-- TOC entry 6094 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.payment_method_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.payment_method_id IS 'Stripe Payment Method ID (pm_xxx)';


--
-- TOC entry 6095 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.amount IS 'Payment amount';


--
-- TOC entry 6096 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.currency; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.currency IS 'Currency code';


--
-- TOC entry 6097 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.error_message; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.error_message IS 'Error message if payment failed';


--
-- TOC entry 6098 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.transaction_data; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.transaction_data IS 'Full Stripe response JSON';


--
-- TOC entry 6099 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.receipt_url; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.receipt_url IS 'Stripe receipt URL';


--
-- TOC entry 6100 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.refund_amount; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.refund_amount IS 'Total refunded amount';


--
-- TOC entry 6101 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.client_ip; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.client_ip IS 'Client IP address';


--
-- TOC entry 6102 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.user_agent; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.user_agent IS 'Client user agent';


--
-- TOC entry 6103 (class 0 OID 0)
-- Dependencies: 325
-- Name: COLUMN appointment_payments.processed_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_payments.processed_at IS 'When payment was processed';


--
-- TOC entry 324 (class 1259 OID 888535)
-- Name: appointment_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.appointment_payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.appointment_payments_id_seq OWNER TO postgres;

--
-- TOC entry 6104 (class 0 OID 0)
-- Dependencies: 324
-- Name: appointment_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.appointment_payments_id_seq OWNED BY public.appointment_payments.id;


--
-- TOC entry 239 (class 1259 OID 887781)
-- Name: appointment_sync_logs; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.appointment_sync_logs OWNER TO postgres;

--
-- TOC entry 6105 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN appointment_sync_logs.sync_details; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.appointment_sync_logs.sync_details IS 'API response, errors, etc.';


--
-- TOC entry 238 (class 1259 OID 887780)
-- Name: appointment_sync_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.appointment_sync_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.appointment_sync_logs_id_seq OWNER TO postgres;

--
-- TOC entry 6106 (class 0 OID 0)
-- Dependencies: 238
-- Name: appointment_sync_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.appointment_sync_logs_id_seq OWNED BY public.appointment_sync_logs.id;


--
-- TOC entry 237 (class 1259 OID 887710)
-- Name: booking_appointments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.booking_appointments (
    id bigint NOT NULL,
    bansal_appointment_id bigint NOT NULL,
    order_hash character varying(191),
    client_id integer,
    consultant_id bigint,
    assigned_by_admin_id integer,
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
    CONSTRAINT booking_appointments_location_check CHECK (((location)::text = ANY ((ARRAY['melbourne'::character varying, 'adelaide'::character varying])::text[]))),
    CONSTRAINT booking_appointments_meeting_type_check CHECK (((meeting_type)::text = ANY ((ARRAY['in_person'::character varying, 'phone'::character varying, 'video'::character varying])::text[]))),
    CONSTRAINT booking_appointments_payment_status_check CHECK (((payment_status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'failed'::character varying, 'refunded'::character varying])::text[]))),
    CONSTRAINT booking_appointments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'paid'::character varying, 'confirmed'::character varying, 'completed'::character varying, 'cancelled'::character varying, 'no_show'::character varying, 'rescheduled'::character varying])::text[]))),
    CONSTRAINT booking_appointments_sync_status_check CHECK (((sync_status)::text = ANY ((ARRAY['new'::character varying, 'synced'::character varying, 'error'::character varying])::text[])))
);


ALTER TABLE public.booking_appointments OWNER TO postgres;

--
-- TOC entry 6107 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.bansal_appointment_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.bansal_appointment_id IS 'ID from Bansal website';


--
-- TOC entry 6108 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.order_hash; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.order_hash IS 'Payment order hash from Bansal';


--
-- TOC entry 6109 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.client_id IS 'FK to admins.id (clients/leads)';


--
-- TOC entry 6110 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.consultant_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.consultant_id IS 'FK to appointment_consultants.id';


--
-- TOC entry 6111 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.assigned_by_admin_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.assigned_by_admin_id IS 'Admin who assigned consultant';


--
-- TOC entry 6112 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.timeslot_full; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.timeslot_full IS 'e.g., "9:00 AM - 9:15 AM"';


--
-- TOC entry 6113 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.inperson_address; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.inperson_address IS 'Legacy: 1=Adelaide, 2=Melbourne';


--
-- TOC entry 6114 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.service_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.service_id IS 'Legacy: 1=Paid, 2=Free';


--
-- TOC entry 6115 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.noe_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.noe_id IS 'Legacy: Nature of Enquiry ID';


--
-- TOC entry 6116 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.enquiry_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.enquiry_type IS 'tr, tourist, education, etc.';


--
-- TOC entry 6117 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN booking_appointments.service_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.booking_appointments.service_type IS 'Display name';


--
-- TOC entry 236 (class 1259 OID 887709)
-- Name: booking_appointments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.booking_appointments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.booking_appointments_id_seq OWNER TO postgres;

--
-- TOC entry 6118 (class 0 OID 0)
-- Dependencies: 236
-- Name: booking_appointments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.booking_appointments_id_seq OWNED BY public.booking_appointments.id;


--
-- TOC entry 261 (class 1259 OID 887917)
-- Name: branches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.branches (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.branches OWNER TO postgres;

--
-- TOC entry 260 (class 1259 OID 887916)
-- Name: branches_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.branches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.branches_id_seq OWNER TO postgres;

--
-- TOC entry 6119 (class 0 OID 0)
-- Dependencies: 260
-- Name: branches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.branches_id_seq OWNED BY public.branches.id;


--
-- TOC entry 225 (class 1259 OID 887612)
-- Name: cache; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache (
    key character varying(191) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 887622)
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cache_locks (
    key character varying(191) NOT NULL,
    owner character varying(191) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO postgres;

--
-- TOC entry 283 (class 1259 OID 888005)
-- Name: checkin_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.checkin_logs (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    walk_in_phone character varying(32),
    walk_in_email character varying(255)
);


ALTER TABLE public.checkin_logs OWNER TO postgres;

--
-- TOC entry 282 (class 1259 OID 888004)
-- Name: checkin_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.checkin_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.checkin_logs_id_seq OWNER TO postgres;

--
-- TOC entry 6120 (class 0 OID 0)
-- Dependencies: 282
-- Name: checkin_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.checkin_logs_id_seq OWNED BY public.checkin_logs.id;


--
-- TOC entry 353 (class 1259 OID 889566)
-- Name: client_access_grants; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_access_grants OWNER TO postgres;

--
-- TOC entry 352 (class 1259 OID 889565)
-- Name: client_access_grants_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_access_grants_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_access_grants_id_seq OWNER TO postgres;

--
-- TOC entry 6121 (class 0 OID 0)
-- Dependencies: 352
-- Name: client_access_grants_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_access_grants_id_seq OWNED BY public.client_access_grants.id;


--
-- TOC entry 265 (class 1259 OID 887933)
-- Name: client_addresses; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_addresses (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    suburb character varying(100),
    country character varying(100) DEFAULT 'Australia'::character varying NOT NULL,
    zip character varying(20)
);


ALTER TABLE public.client_addresses OWNER TO postgres;

--
-- TOC entry 264 (class 1259 OID 887932)
-- Name: client_addresses_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_addresses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_addresses_id_seq OWNER TO postgres;

--
-- TOC entry 6122 (class 0 OID 0)
-- Dependencies: 264
-- Name: client_addresses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_addresses_id_seq OWNED BY public.client_addresses.id;


--
-- TOC entry 327 (class 1259 OID 888580)
-- Name: client_art_references; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_art_references OWNER TO postgres;

--
-- TOC entry 6123 (class 0 OID 0)
-- Dependencies: 327
-- Name: COLUMN client_art_references.status_of_file; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_art_references.status_of_file IS 'submission_pending, submission_done, hearing_invitation_sent, waiting_for_hearing, hearing, decided, withdrawn';


--
-- TOC entry 6124 (class 0 OID 0)
-- Dependencies: 327
-- Name: COLUMN client_art_references.hearing_time; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_art_references.hearing_time IS 'e.g. 10:30 am (NSW time)';


--
-- TOC entry 6125 (class 0 OID 0)
-- Dependencies: 327
-- Name: COLUMN client_art_references.member_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_art_references.member_name IS 'Tribunal member';


--
-- TOC entry 326 (class 1259 OID 888579)
-- Name: client_art_references_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_art_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_art_references_id_seq OWNER TO postgres;

--
-- TOC entry 6126 (class 0 OID 0)
-- Dependencies: 326
-- Name: client_art_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_art_references_id_seq OWNED BY public.client_art_references.id;


--
-- TOC entry 295 (class 1259 OID 888085)
-- Name: client_contacts; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_contacts OWNER TO postgres;

--
-- TOC entry 294 (class 1259 OID 888084)
-- Name: client_contacts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_contacts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_contacts_id_seq OWNER TO postgres;

--
-- TOC entry 6127 (class 0 OID 0)
-- Dependencies: 294
-- Name: client_contacts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_contacts_id_seq OWNED BY public.client_contacts.id;


--
-- TOC entry 299 (class 1259 OID 888126)
-- Name: client_emails; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_emails OWNER TO postgres;

--
-- TOC entry 298 (class 1259 OID 888125)
-- Name: client_emails_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_emails_id_seq OWNER TO postgres;

--
-- TOC entry 6128 (class 0 OID 0)
-- Dependencies: 298
-- Name: client_emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_emails_id_seq OWNED BY public.client_emails.id;


--
-- TOC entry 245 (class 1259 OID 887831)
-- Name: client_experiences; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_experiences (
    id bigint NOT NULL,
    client_id bigint,
    job_country character varying(191),
    job_type character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fte_multiplier numeric(3,2) DEFAULT '1'::numeric NOT NULL
);


ALTER TABLE public.client_experiences OWNER TO postgres;

--
-- TOC entry 6129 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN client_experiences.fte_multiplier; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_experiences.fte_multiplier IS 'Full-time equivalent multiplier (1.00 = full-time, 0.50 = half-time, etc.)';


--
-- TOC entry 244 (class 1259 OID 887830)
-- Name: client_experiences_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_experiences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_experiences_id_seq OWNER TO postgres;

--
-- TOC entry 6130 (class 0 OID 0)
-- Dependencies: 244
-- Name: client_experiences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_experiences_id_seq OWNED BY public.client_experiences.id;


--
-- TOC entry 331 (class 1259 OID 888988)
-- Name: client_matter_payment_forms_verifications; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_matter_payment_forms_verifications OWNER TO postgres;

--
-- TOC entry 6131 (class 0 OID 0)
-- Dependencies: 331
-- Name: COLUMN client_matter_payment_forms_verifications.verified_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matter_payment_forms_verifications.verified_by IS 'Migration Agent (staff id) who verified';


--
-- TOC entry 6132 (class 0 OID 0)
-- Dependencies: 331
-- Name: COLUMN client_matter_payment_forms_verifications.note; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matter_payment_forms_verifications.note IS 'Optional text from Migration Agent';


--
-- TOC entry 330 (class 1259 OID 888987)
-- Name: client_matter_payment_forms_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_matter_payment_forms_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_matter_payment_forms_verifications_id_seq OWNER TO postgres;

--
-- TOC entry 6133 (class 0 OID 0)
-- Dependencies: 330
-- Name: client_matter_payment_forms_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_matter_payment_forms_verifications_id_seq OWNED BY public.client_matter_payment_forms_verifications.id;


--
-- TOC entry 337 (class 1259 OID 889332)
-- Name: client_matter_references; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_matter_references OWNER TO postgres;

--
-- TOC entry 336 (class 1259 OID 889331)
-- Name: client_matter_references_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_matter_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_matter_references_id_seq OWNER TO postgres;

--
-- TOC entry 6134 (class 0 OID 0)
-- Dependencies: 336
-- Name: client_matter_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_matter_references_id_seq OWNED BY public.client_matter_references.id;


--
-- TOC entry 253 (class 1259 OID 887881)
-- Name: client_matters; Type: TABLE; Schema: public; Owner: postgres
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
    parents_checklist_status character varying(32)
);


ALTER TABLE public.client_matters OWNER TO postgres;

--
-- TOC entry 6135 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.office_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.office_id IS 'Manually assigned handling office';


--
-- TOC entry 6136 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.tr_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.tr_checklist_status IS 'TR sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6137 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.visitor_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.visitor_checklist_status IS 'Visitor sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6138 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.student_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.student_checklist_status IS 'Student sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6139 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.pr_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.pr_checklist_status IS 'PR sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6140 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.employer_sponsored_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.employer_sponsored_checklist_status IS 'Employer Sponsored sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6141 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.deadline; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.deadline IS 'Optional matter deadline; null when not set';


--
-- TOC entry 6142 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.partner_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.partner_checklist_status IS 'Partner Visa sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 6143 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN client_matters.parents_checklist_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_matters.parents_checklist_status IS 'Parents Visa sheet checklist status: active, hold, convert_to_client, discontinue';


--
-- TOC entry 252 (class 1259 OID 887880)
-- Name: client_matters_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_matters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_matters_id_seq OWNER TO postgres;

--
-- TOC entry 6144 (class 0 OID 0)
-- Dependencies: 252
-- Name: client_matters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_matters_id_seq OWNED BY public.client_matters.id;


--
-- TOC entry 269 (class 1259 OID 887949)
-- Name: client_occupations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_occupations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    anzsco_occupation_id bigint
);


ALTER TABLE public.client_occupations OWNER TO postgres;

--
-- TOC entry 268 (class 1259 OID 887948)
-- Name: client_occupations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_occupations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_occupations_id_seq OWNER TO postgres;

--
-- TOC entry 6145 (class 0 OID 0)
-- Dependencies: 268
-- Name: client_occupations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_occupations_id_seq OWNED BY public.client_occupations.id;


--
-- TOC entry 233 (class 1259 OID 887680)
-- Name: client_passport_informations; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_passport_informations OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 887679)
-- Name: client_passport_informations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_passport_informations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_passport_informations_id_seq OWNER TO postgres;

--
-- TOC entry 6146 (class 0 OID 0)
-- Dependencies: 232
-- Name: client_passport_informations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_passport_informations_id_seq OWNED BY public.client_passport_informations.id;


--
-- TOC entry 241 (class 1259 OID 887811)
-- Name: client_qualifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_qualifications (
    id bigint NOT NULL,
    client_id bigint,
    country character varying(191),
    relevant_qualification boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.client_qualifications OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 887810)
-- Name: client_qualifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_qualifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_qualifications_id_seq OWNER TO postgres;

--
-- TOC entry 6147 (class 0 OID 0)
-- Dependencies: 240
-- Name: client_qualifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_qualifications_id_seq OWNED BY public.client_qualifications.id;


--
-- TOC entry 243 (class 1259 OID 887822)
-- Name: client_spouse_details; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.client_spouse_details OWNER TO postgres;

--
-- TOC entry 6148 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN client_spouse_details.is_citizen; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_spouse_details.is_citizen IS 'Partner is Australian citizen';


--
-- TOC entry 6149 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN client_spouse_details.has_pr; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_spouse_details.has_pr IS 'Partner has Australian Permanent Residency (PR)';


--
-- TOC entry 6150 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN client_spouse_details.dob; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_spouse_details.dob IS 'Partner date of birth for points calculation';


--
-- TOC entry 6151 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN client_spouse_details.related_client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_spouse_details.related_client_id IS 'Reference to the partner client ID for EOI calculation';


--
-- TOC entry 242 (class 1259 OID 887821)
-- Name: client_spouse_details_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_spouse_details_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_spouse_details_id_seq OWNER TO postgres;

--
-- TOC entry 6152 (class 0 OID 0)
-- Dependencies: 242
-- Name: client_spouse_details_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_spouse_details_id_seq OWNED BY public.client_spouse_details.id;


--
-- TOC entry 247 (class 1259 OID 887840)
-- Name: client_testscore; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_testscore (
    id bigint NOT NULL,
    overall_score integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    proficiency_level character varying(191),
    proficiency_points integer
);


ALTER TABLE public.client_testscore OWNER TO postgres;

--
-- TOC entry 6153 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN client_testscore.proficiency_level; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_testscore.proficiency_level IS 'Calculated English proficiency level (e.g., Competent English, Proficient English, Superior English)';


--
-- TOC entry 6154 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN client_testscore.proficiency_points; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.client_testscore.proficiency_points IS 'Points awarded for this proficiency level (0, 10, or 20)';


--
-- TOC entry 246 (class 1259 OID 887839)
-- Name: client_testscore_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_testscore_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_testscore_id_seq OWNER TO postgres;

--
-- TOC entry 6155 (class 0 OID 0)
-- Dependencies: 246
-- Name: client_testscore_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_testscore_id_seq OWNED BY public.client_testscore.id;


--
-- TOC entry 285 (class 1259 OID 888013)
-- Name: client_visa_countries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.client_visa_countries (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.client_visa_countries OWNER TO postgres;

--
-- TOC entry 284 (class 1259 OID 888012)
-- Name: client_visa_countries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.client_visa_countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_visa_countries_id_seq OWNER TO postgres;

--
-- TOC entry 6156 (class 0 OID 0)
-- Dependencies: 284
-- Name: client_visa_countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.client_visa_countries_id_seq OWNED BY public.client_visa_countries.id;


--
-- TOC entry 319 (class 1259 OID 888428)
-- Name: clientportal_details_audit; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.clientportal_details_audit (
    id bigint NOT NULL,
    client_id integer NOT NULL,
    meta_key character varying(100) NOT NULL,
    old_value text,
    new_value text,
    meta_order integer,
    meta_type character varying(50),
    action character varying(20) DEFAULT 'update'::character varying NOT NULL,
    updated_by integer,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.clientportal_details_audit OWNER TO postgres;

--
-- TOC entry 6157 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.client_id IS 'FK to admins.id';


--
-- TOC entry 6158 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.meta_key; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.meta_key IS 'Field name that was changed';


--
-- TOC entry 6159 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.old_value; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.old_value IS 'Previous value';


--
-- TOC entry 6160 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.new_value; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.new_value IS 'New value';


--
-- TOC entry 6161 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.meta_order; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.meta_order IS 'Order of the value that changed';


--
-- TOC entry 6162 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.meta_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.meta_type IS 'Type of the value that changed';


--
-- TOC entry 6163 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.action; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.action IS 'create, update, delete';


--
-- TOC entry 6164 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.updated_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.updated_by IS 'FK to admins.id - who made the change';


--
-- TOC entry 6165 (class 0 OID 0)
-- Dependencies: 319
-- Name: COLUMN clientportal_details_audit.updated_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.clientportal_details_audit.updated_at IS 'When the change was made';


--
-- TOC entry 318 (class 1259 OID 888427)
-- Name: clientportal_details_audit_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.clientportal_details_audit_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.clientportal_details_audit_id_seq OWNER TO postgres;

--
-- TOC entry 6166 (class 0 OID 0)
-- Dependencies: 318
-- Name: clientportal_details_audit_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.clientportal_details_audit_id_seq OWNED BY public.clientportal_details_audit.id;


--
-- TOC entry 323 (class 1259 OID 888508)
-- Name: companies; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.companies OWNER TO postgres;

--
-- TOC entry 6167 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.admin_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.admin_id IS 'Reference to admins.id - one-to-one relationship with company lead/client';


--
-- TOC entry 6168 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.company_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.company_name IS 'Company name';


--
-- TOC entry 6169 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.trading_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.trading_name IS 'Trading name if different from company name';


--
-- TOC entry 6170 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies."ABN_number"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies."ABN_number" IS 'Australian Business Number (11 digits)';


--
-- TOC entry 6171 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies."ACN"; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies."ACN" IS 'Australian Company Number (9 digits)';


--
-- TOC entry 6172 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.company_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.company_type IS 'Business type: Sole Trader, Partnership, Proprietary Company, etc.';


--
-- TOC entry 6173 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.company_website; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.company_website IS 'Company website URL';


--
-- TOC entry 6174 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.contact_person_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.contact_person_id IS 'Reference to admins.id of the primary contact person';


--
-- TOC entry 6175 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.contact_person_position; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.contact_person_position IS 'Position/Title of primary contact person (e.g., HR Manager, Director)';


--
-- TOC entry 6176 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN companies.trn; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.companies.trn IS 'Training Reference Number';


--
-- TOC entry 322 (class 1259 OID 888507)
-- Name: companies_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.companies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.companies_id_seq OWNER TO postgres;

--
-- TOC entry 6177 (class 0 OID 0)
-- Dependencies: 322
-- Name: companies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.companies_id_seq OWNED BY public.companies.id;


--
-- TOC entry 349 (class 1259 OID 889506)
-- Name: company_directors; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.company_directors OWNER TO postgres;

--
-- TOC entry 6178 (class 0 OID 0)
-- Dependencies: 349
-- Name: COLUMN company_directors.director_client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.company_directors.director_client_id IS 'FK to admins.id when director is existing client/lead';


--
-- TOC entry 348 (class 1259 OID 889505)
-- Name: company_directors_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.company_directors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_directors_id_seq OWNER TO postgres;

--
-- TOC entry 6179 (class 0 OID 0)
-- Dependencies: 348
-- Name: company_directors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_directors_id_seq OWNED BY public.company_directors.id;


--
-- TOC entry 351 (class 1259 OID 889526)
-- Name: company_nominations; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.company_nominations OWNER TO postgres;

--
-- TOC entry 6180 (class 0 OID 0)
-- Dependencies: 351
-- Name: COLUMN company_nominations.nominated_client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.company_nominations.nominated_client_id IS 'FK to admins.id when person is client/lead';


--
-- TOC entry 6181 (class 0 OID 0)
-- Dependencies: 351
-- Name: COLUMN company_nominations.nominated_person_name; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.company_nominations.nominated_person_name IS 'Name when person not in system';


--
-- TOC entry 350 (class 1259 OID 889525)
-- Name: company_nominations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.company_nominations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_nominations_id_seq OWNER TO postgres;

--
-- TOC entry 6182 (class 0 OID 0)
-- Dependencies: 350
-- Name: company_nominations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_nominations_id_seq OWNED BY public.company_nominations.id;


--
-- TOC entry 357 (class 1259 OID 889632)
-- Name: company_sponsorships; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.company_sponsorships OWNER TO postgres;

--
-- TOC entry 356 (class 1259 OID 889631)
-- Name: company_sponsorships_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.company_sponsorships_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_sponsorships_id_seq OWNER TO postgres;

--
-- TOC entry 6183 (class 0 OID 0)
-- Dependencies: 356
-- Name: company_sponsorships_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_sponsorships_id_seq OWNED BY public.company_sponsorships.id;


--
-- TOC entry 347 (class 1259 OID 889484)
-- Name: company_trading_names; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.company_trading_names OWNER TO postgres;

--
-- TOC entry 346 (class 1259 OID 889483)
-- Name: company_trading_names_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.company_trading_names_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.company_trading_names_id_seq OWNER TO postgres;

--
-- TOC entry 6184 (class 0 OID 0)
-- Dependencies: 346
-- Name: company_trading_names_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.company_trading_names_id_seq OWNED BY public.company_trading_names.id;


--
-- TOC entry 291 (class 1259 OID 888037)
-- Name: device_tokens; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.device_tokens OWNER TO postgres;

--
-- TOC entry 290 (class 1259 OID 888036)
-- Name: device_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.device_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.device_tokens_id_seq OWNER TO postgres;

--
-- TOC entry 6185 (class 0 OID 0)
-- Dependencies: 290
-- Name: device_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.device_tokens_id_seq OWNED BY public.device_tokens.id;


--
-- TOC entry 311 (class 1259 OID 888324)
-- Name: document_notes; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.document_notes OWNER TO postgres;

--
-- TOC entry 6186 (class 0 OID 0)
-- Dependencies: 311
-- Name: COLUMN document_notes.created_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.document_notes.created_by IS 'Admin who performed the action';


--
-- TOC entry 6187 (class 0 OID 0)
-- Dependencies: 311
-- Name: COLUMN document_notes.action_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.document_notes.action_type IS 'associated, detached, status_changed, etc.';


--
-- TOC entry 6188 (class 0 OID 0)
-- Dependencies: 311
-- Name: COLUMN document_notes.note; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.document_notes.note IS 'User-provided note or system-generated description';


--
-- TOC entry 6189 (class 0 OID 0)
-- Dependencies: 311
-- Name: COLUMN document_notes.metadata; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.document_notes.metadata IS 'Additional data: entity type/id, old values, etc.';


--
-- TOC entry 310 (class 1259 OID 888323)
-- Name: document_notes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.document_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.document_notes_id_seq OWNER TO postgres;

--
-- TOC entry 6190 (class 0 OID 0)
-- Dependencies: 310
-- Name: document_notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.document_notes_id_seq OWNED BY public.document_notes.id;


--
-- TOC entry 271 (class 1259 OID 887957)
-- Name: documents; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.documents (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    created_by integer,
    office_id integer,
    form956_id bigint,
    cp_list_id bigint,
    cp_rejection_reason text,
    cp_doc_status smallint,
    lead_id bigint
);


ALTER TABLE public.documents OWNER TO postgres;

--
-- TOC entry 6191 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN documents.office_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.documents.office_id IS 'Office for ad-hoc documents (without matter)';


--
-- TOC entry 6192 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN documents.cp_list_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.documents.cp_list_id IS 'FK to cp_doc_checklist.id';


--
-- TOC entry 6193 (class 0 OID 0)
-- Dependencies: 271
-- Name: COLUMN documents.cp_doc_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.documents.cp_doc_status IS '0=InProgress, 1=Approved, 2=Rejected';


--
-- TOC entry 270 (class 1259 OID 887956)
-- Name: documents_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.documents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.documents_id_seq OWNER TO postgres;

--
-- TOC entry 6194 (class 0 OID 0)
-- Dependencies: 270
-- Name: documents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.documents_id_seq OWNED BY public.documents.id;


--
-- TOC entry 315 (class 1259 OID 888383)
-- Name: email_label_email_log; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.email_label_email_log (
    id bigint CONSTRAINT email_label_mail_report_id_not_null NOT NULL,
    email_log_id bigint CONSTRAINT email_label_mail_report_mail_report_id_not_null NOT NULL,
    email_label_id bigint CONSTRAINT email_label_mail_report_email_label_id_not_null NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.email_label_email_log OWNER TO postgres;

--
-- TOC entry 314 (class 1259 OID 888382)
-- Name: email_label_mail_report_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.email_label_mail_report_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.email_label_mail_report_id_seq OWNER TO postgres;

--
-- TOC entry 6195 (class 0 OID 0)
-- Dependencies: 314
-- Name: email_label_mail_report_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.email_label_mail_report_id_seq OWNED BY public.email_label_email_log.id;


--
-- TOC entry 313 (class 1259 OID 888350)
-- Name: email_labels; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.email_labels OWNER TO postgres;

--
-- TOC entry 312 (class 1259 OID 888349)
-- Name: email_labels_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.email_labels_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.email_labels_id_seq OWNER TO postgres;

--
-- TOC entry 6196 (class 0 OID 0)
-- Dependencies: 312
-- Name: email_labels_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.email_labels_id_seq OWNED BY public.email_labels.id;


--
-- TOC entry 317 (class 1259 OID 888397)
-- Name: email_log_attachments; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.email_log_attachments OWNER TO postgres;

--
-- TOC entry 275 (class 1259 OID 887973)
-- Name: email_logs; Type: TABLE; Schema: public; Owner: postgres
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
    CONSTRAINT mail_reports_sentiment_check CHECK (((sentiment)::text = ANY ((ARRAY['positive'::character varying, 'neutral'::character varying, 'negative'::character varying])::text[])))
);


ALTER TABLE public.email_logs OWNER TO postgres;

--
-- TOC entry 345 (class 1259 OID 889462)
-- Name: email_templates; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.email_templates OWNER TO postgres;

--
-- TOC entry 344 (class 1259 OID 889461)
-- Name: email_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.email_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.email_templates_id_seq OWNER TO postgres;

--
-- TOC entry 6197 (class 0 OID 0)
-- Dependencies: 344
-- Name: email_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.email_templates_id_seq OWNED BY public.email_templates.id;


--
-- TOC entry 301 (class 1259 OID 888147)
-- Name: email_verifications; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.email_verifications OWNER TO postgres;

--
-- TOC entry 300 (class 1259 OID 888146)
-- Name: email_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.email_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.email_verifications_id_seq OWNER TO postgres;

--
-- TOC entry 6198 (class 0 OID 0)
-- Dependencies: 300
-- Name: email_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.email_verifications_id_seq OWNED BY public.email_verifications.id;


--
-- TOC entry 273 (class 1259 OID 887965)
-- Name: emails; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.emails (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.emails OWNER TO postgres;

--
-- TOC entry 272 (class 1259 OID 887964)
-- Name: emails_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.emails_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.emails_id_seq OWNER TO postgres;

--
-- TOC entry 6199 (class 0 OID 0)
-- Dependencies: 272
-- Name: emails_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.emails_id_seq OWNED BY public.emails.id;


--
-- TOC entry 231 (class 1259 OID 887661)
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.failed_jobs OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 887660)
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO postgres;

--
-- TOC entry 6200 (class 0 OID 0)
-- Dependencies: 230
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- TOC entry 359 (class 1259 OID 889655)
-- Name: front_desk_check_ins; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.front_desk_check_ins OWNER TO postgres;

--
-- TOC entry 6201 (class 0 OID 0)
-- Dependencies: 359
-- Name: COLUMN front_desk_check_ins.admin_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.front_desk_check_ins.admin_id IS 'Staff ID (staff table)';


--
-- TOC entry 6202 (class 0 OID 0)
-- Dependencies: 359
-- Name: COLUMN front_desk_check_ins.client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.front_desk_check_ins.client_id IS 'admins.id where type=client';


--
-- TOC entry 6203 (class 0 OID 0)
-- Dependencies: 359
-- Name: COLUMN front_desk_check_ins.lead_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.front_desk_check_ins.lead_id IS 'admins.id where type=lead';


--
-- TOC entry 6204 (class 0 OID 0)
-- Dependencies: 359
-- Name: COLUMN front_desk_check_ins.appointment_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.front_desk_check_ins.appointment_id IS 'booking_appointments.id';


--
-- TOC entry 358 (class 1259 OID 889654)
-- Name: front_desk_check_ins_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.front_desk_check_ins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.front_desk_check_ins_id_seq OWNER TO postgres;

--
-- TOC entry 6205 (class 0 OID 0)
-- Dependencies: 358
-- Name: front_desk_check_ins_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.front_desk_check_ins_id_seq OWNED BY public.front_desk_check_ins.id;


--
-- TOC entry 229 (class 1259 OID 887646)
-- Name: job_batches; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.job_batches OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 887631)
-- Name: jobs; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.jobs OWNER TO postgres;

--
-- TOC entry 227 (class 1259 OID 887630)
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO postgres;

--
-- TOC entry 6206 (class 0 OID 0)
-- Dependencies: 227
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- TOC entry 341 (class 1259 OID 889400)
-- Name: lead_matter_references; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.lead_matter_references OWNER TO postgres;

--
-- TOC entry 340 (class 1259 OID 889399)
-- Name: lead_matter_references_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.lead_matter_references_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.lead_matter_references_id_seq OWNER TO postgres;

--
-- TOC entry 6207 (class 0 OID 0)
-- Dependencies: 340
-- Name: lead_matter_references_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.lead_matter_references_id_seq OWNED BY public.lead_matter_references.id;


--
-- TOC entry 343 (class 1259 OID 889436)
-- Name: lead_reminders; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.lead_reminders OWNER TO postgres;

--
-- TOC entry 6208 (class 0 OID 0)
-- Dependencies: 343
-- Name: COLUMN lead_reminders.type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.lead_reminders.type IS 'email, sms, or phone';


--
-- TOC entry 342 (class 1259 OID 889435)
-- Name: lead_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.lead_reminders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.lead_reminders_id_seq OWNER TO postgres;

--
-- TOC entry 6209 (class 0 OID 0)
-- Dependencies: 342
-- Name: lead_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.lead_reminders_id_seq OWNED BY public.lead_reminders.id;


--
-- TOC entry 316 (class 1259 OID 888396)
-- Name: mail_report_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mail_report_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mail_report_attachments_id_seq OWNER TO postgres;

--
-- TOC entry 6210 (class 0 OID 0)
-- Dependencies: 316
-- Name: mail_report_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mail_report_attachments_id_seq OWNED BY public.email_log_attachments.id;


--
-- TOC entry 274 (class 1259 OID 887972)
-- Name: mail_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.mail_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.mail_reports_id_seq OWNER TO postgres;

--
-- TOC entry 6211 (class 0 OID 0)
-- Dependencies: 274
-- Name: mail_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.mail_reports_id_seq OWNED BY public.email_logs.id;


--
-- TOC entry 339 (class 1259 OID 889373)
-- Name: matter_reminders; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.matter_reminders OWNER TO postgres;

--
-- TOC entry 6212 (class 0 OID 0)
-- Dependencies: 339
-- Name: COLUMN matter_reminders.type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.matter_reminders.type IS 'email, sms, or phone';


--
-- TOC entry 6213 (class 0 OID 0)
-- Dependencies: 339
-- Name: COLUMN matter_reminders.reminded_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.matter_reminders.reminded_by IS 'Staff who sent the reminder';


--
-- TOC entry 338 (class 1259 OID 889372)
-- Name: matter_reminders_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.matter_reminders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.matter_reminders_id_seq OWNER TO postgres;

--
-- TOC entry 6214 (class 0 OID 0)
-- Dependencies: 338
-- Name: matter_reminders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.matter_reminders_id_seq OWNED BY public.matter_reminders.id;


--
-- TOC entry 281 (class 1259 OID 887997)
-- Name: matters; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.matters (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_for_company boolean DEFAULT false,
    workflow_id bigint
);


ALTER TABLE public.matters OWNER TO postgres;

--
-- TOC entry 6215 (class 0 OID 0)
-- Dependencies: 281
-- Name: COLUMN matters.is_for_company; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.matters.is_for_company IS 'If true, this matter is only available for company clients. If false/null, available for personal clients.';


--
-- TOC entry 280 (class 1259 OID 887996)
-- Name: matters_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.matters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.matters_id_seq OWNER TO postgres;

--
-- TOC entry 6216 (class 0 OID 0)
-- Dependencies: 280
-- Name: matters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.matters_id_seq OWNED BY public.matters.id;


--
-- TOC entry 333 (class 1259 OID 889009)
-- Name: message_attachments; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.message_attachments OWNER TO postgres;

--
-- TOC entry 332 (class 1259 OID 889008)
-- Name: message_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.message_attachments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.message_attachments_id_seq OWNER TO postgres;

--
-- TOC entry 6217 (class 0 OID 0)
-- Dependencies: 332
-- Name: message_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.message_attachments_id_seq OWNED BY public.message_attachments.id;


--
-- TOC entry 303 (class 1259 OID 888174)
-- Name: message_recipients; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.message_recipients OWNER TO postgres;

--
-- TOC entry 302 (class 1259 OID 888173)
-- Name: message_recipients_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.message_recipients_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.message_recipients_id_seq OWNER TO postgres;

--
-- TOC entry 6218 (class 0 OID 0)
-- Dependencies: 302
-- Name: message_recipients_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.message_recipients_id_seq OWNED BY public.message_recipients.id;


--
-- TOC entry 267 (class 1259 OID 887941)
-- Name: messages; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.messages (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.messages OWNER TO postgres;

--
-- TOC entry 266 (class 1259 OID 887940)
-- Name: messages_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.messages_id_seq OWNER TO postgres;

--
-- TOC entry 6219 (class 0 OID 0)
-- Dependencies: 266
-- Name: messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.messages_id_seq OWNED BY public.messages.id;


--
-- TOC entry 220 (class 1259 OID 887564)
-- Name: migrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(191) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 887563)
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO postgres;

--
-- TOC entry 6220 (class 0 OID 0)
-- Dependencies: 219
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- TOC entry 355 (class 1259 OID 889619)
-- Name: nomination_document_types; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.nomination_document_types OWNER TO postgres;

--
-- TOC entry 354 (class 1259 OID 889618)
-- Name: nomination_document_types_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nomination_document_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nomination_document_types_id_seq OWNER TO postgres;

--
-- TOC entry 6221 (class 0 OID 0)
-- Dependencies: 354
-- Name: nomination_document_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nomination_document_types_id_seq OWNED BY public.nomination_document_types.id;


--
-- TOC entry 257 (class 1259 OID 887899)
-- Name: notes; Type: TABLE; Schema: public; Owner: postgres
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
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.notes OWNER TO postgres;

--
-- TOC entry 256 (class 1259 OID 887898)
-- Name: notes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notes_id_seq OWNER TO postgres;

--
-- TOC entry 6222 (class 0 OID 0)
-- Dependencies: 256
-- Name: notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notes_id_seq OWNED BY public.notes.id;


--
-- TOC entry 255 (class 1259 OID 887890)
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id bigint NOT NULL,
    receiver_id bigint,
    notification_type character varying(191),
    receiver_status smallint DEFAULT '0'::smallint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- TOC entry 254 (class 1259 OID 887889)
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- TOC entry 6223 (class 0 OID 0)
-- Dependencies: 254
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- TOC entry 223 (class 1259 OID 887593)
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_reset_tokens (
    email character varying(191) NOT NULL,
    token character varying(191) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO postgres;

--
-- TOC entry 297 (class 1259 OID 888095)
-- Name: phone_verifications; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.phone_verifications OWNER TO postgres;

--
-- TOC entry 296 (class 1259 OID 888094)
-- Name: phone_verifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.phone_verifications_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.phone_verifications_id_seq OWNER TO postgres;

--
-- TOC entry 6224 (class 0 OID 0)
-- Dependencies: 296
-- Name: phone_verifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.phone_verifications_id_seq OWNED BY public.phone_verifications.id;


--
-- TOC entry 293 (class 1259 OID 888060)
-- Name: refresh_tokens; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.refresh_tokens OWNER TO postgres;

--
-- TOC entry 292 (class 1259 OID 888059)
-- Name: refresh_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.refresh_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.refresh_tokens_id_seq OWNER TO postgres;

--
-- TOC entry 6225 (class 0 OID 0)
-- Dependencies: 292
-- Name: refresh_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.refresh_tokens_id_seq OWNED BY public.refresh_tokens.id;


--
-- TOC entry 224 (class 1259 OID 887600)
-- Name: sessions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sessions (
    id character varying(191) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO postgres;

--
-- TOC entry 251 (class 1259 OID 887872)
-- Name: signers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.signers (
    id bigint NOT NULL,
    status character varying(20),
    reminder_count integer DEFAULT 0,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    email_template character varying(191),
    email_subject character varying(191),
    email_message text,
    from_email character varying(191),
    cancelled_at timestamp(0) without time zone
);


ALTER TABLE public.signers OWNER TO postgres;

--
-- TOC entry 250 (class 1259 OID 887871)
-- Name: signers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.signers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.signers_id_seq OWNER TO postgres;

--
-- TOC entry 6226 (class 0 OID 0)
-- Dependencies: 250
-- Name: signers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.signers_id_seq OWNED BY public.signers.id;


--
-- TOC entry 307 (class 1259 OID 888257)
-- Name: sms_logs; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.sms_logs OWNER TO postgres;

--
-- TOC entry 6227 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.client_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.client_id IS 'Client who received SMS';


--
-- TOC entry 6228 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.client_contact_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.client_contact_id IS 'Specific contact record';


--
-- TOC entry 6229 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.sender_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.sender_id IS 'Admin user who sent SMS';


--
-- TOC entry 6230 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.recipient_phone; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.recipient_phone IS 'Original phone number entered';


--
-- TOC entry 6231 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.country_code; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.country_code IS 'Country code';


--
-- TOC entry 6232 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.formatted_phone; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.formatted_phone IS 'Final formatted number sent to provider';


--
-- TOC entry 6233 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.message_content; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.message_content IS 'Full SMS message content';


--
-- TOC entry 6234 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.message_type; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.message_type IS 'Type of SMS message';


--
-- TOC entry 6235 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.template_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.template_id IS 'Template used if applicable';


--
-- TOC entry 6236 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.provider; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.provider IS 'cellcast or twilio';


--
-- TOC entry 6237 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.provider_message_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.provider_message_id IS 'Message ID from provider (SID)';


--
-- TOC entry 6238 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.status IS 'Delivery status';


--
-- TOC entry 6239 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.error_message; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.error_message IS 'Error details if failed';


--
-- TOC entry 6240 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.cost; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.cost IS 'SMS cost';


--
-- TOC entry 6241 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.sent_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.sent_at IS 'When SMS was sent to provider';


--
-- TOC entry 6242 (class 0 OID 0)
-- Dependencies: 307
-- Name: COLUMN sms_logs.delivered_at; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_logs.delivered_at IS 'When SMS was delivered';


--
-- TOC entry 306 (class 1259 OID 888256)
-- Name: sms_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sms_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sms_logs_id_seq OWNER TO postgres;

--
-- TOC entry 6243 (class 0 OID 0)
-- Dependencies: 306
-- Name: sms_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sms_logs_id_seq OWNED BY public.sms_logs.id;


--
-- TOC entry 309 (class 1259 OID 888287)
-- Name: sms_templates; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.sms_templates OWNER TO postgres;

--
-- TOC entry 6244 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.title; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.title IS 'Template name';


--
-- TOC entry 6245 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.message; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.message IS 'SMS message content with variables';


--
-- TOC entry 6246 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.variables; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.variables IS 'Comma-separated list of variables';


--
-- TOC entry 6247 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.category; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.category IS 'verification, reminder, notification, manual';


--
-- TOC entry 6248 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.alias; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.alias IS 'Unique identifier for programmatic access';


--
-- TOC entry 6249 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.is_active; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.is_active IS 'Whether template is active';


--
-- TOC entry 6250 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.usage_count; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.usage_count IS 'Number of times template has been used';


--
-- TOC entry 6251 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.created_by; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.created_by IS 'Admin user who created template';


--
-- TOC entry 6252 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN sms_templates.description; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.sms_templates.description IS 'Template description for internal reference';


--
-- TOC entry 308 (class 1259 OID 888286)
-- Name: sms_templates_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sms_templates_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sms_templates_id_seq OWNER TO postgres;

--
-- TOC entry 6253 (class 0 OID 0)
-- Dependencies: 308
-- Name: sms_templates_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sms_templates_id_seq OWNED BY public.sms_templates.id;


--
-- TOC entry 329 (class 1259 OID 888631)
-- Name: staff; Type: TABLE; Schema: public; Owner: postgres
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
    is_migration_agent smallint DEFAULT '0'::smallint NOT NULL,
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
    sheet_access text,
    quick_access_enabled boolean DEFAULT false NOT NULL,
    grant_super_admin_access boolean
);


ALTER TABLE public.staff OWNER TO postgres;

--
-- TOC entry 328 (class 1259 OID 888630)
-- Name: staff_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.staff_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.staff_id_seq OWNER TO postgres;

--
-- TOC entry 6254 (class 0 OID 0)
-- Dependencies: 328
-- Name: staff_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.staff_id_seq OWNED BY public.staff.id;


--
-- TOC entry 321 (class 1259 OID 888488)
-- Name: staff_login_logs; Type: TABLE; Schema: public; Owner: postgres
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


ALTER TABLE public.staff_login_logs OWNER TO postgres;

--
-- TOC entry 279 (class 1259 OID 887989)
-- Name: tags; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tags (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tag_type character varying(20) DEFAULT 'normal'::character varying NOT NULL,
    is_hidden boolean DEFAULT false NOT NULL
);


ALTER TABLE public.tags OWNER TO postgres;

--
-- TOC entry 278 (class 1259 OID 887988)
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tags_id_seq OWNER TO postgres;

--
-- TOC entry 6255 (class 0 OID 0)
-- Dependencies: 278
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tags_id_seq OWNED BY public.tags.id;


--
-- TOC entry 259 (class 1259 OID 887909)
-- Name: teams; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.teams (
    id bigint NOT NULL,
    name character varying(191),
    color character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.teams OWNER TO postgres;

--
-- TOC entry 258 (class 1259 OID 887908)
-- Name: teams_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.teams_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.teams_id_seq OWNER TO postgres;

--
-- TOC entry 6256 (class 0 OID 0)
-- Dependencies: 258
-- Name: teams_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.teams_id_seq OWNED BY public.teams.id;


--
-- TOC entry 320 (class 1259 OID 888487)
-- Name: user_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_logs_id_seq OWNER TO postgres;

--
-- TOC entry 6257 (class 0 OID 0)
-- Dependencies: 320
-- Name: user_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_logs_id_seq OWNED BY public.staff_login_logs.id;


--
-- TOC entry 263 (class 1259 OID 887925)
-- Name: user_roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_roles (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.user_roles OWNER TO postgres;

--
-- TOC entry 262 (class 1259 OID 887924)
-- Name: user_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_roles_id_seq OWNER TO postgres;

--
-- TOC entry 6258 (class 0 OID 0)
-- Dependencies: 262
-- Name: user_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_roles_id_seq OWNED BY public.user_roles.id;


--
-- TOC entry 287 (class 1259 OID 888021)
-- Name: workflow_stages; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.workflow_stages (
    id bigint NOT NULL,
    name character varying(191),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    sort_order integer,
    workflow_id bigint
);


ALTER TABLE public.workflow_stages OWNER TO postgres;

--
-- TOC entry 286 (class 1259 OID 888020)
-- Name: workflow_stages_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.workflow_stages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.workflow_stages_id_seq OWNER TO postgres;

--
-- TOC entry 6259 (class 0 OID 0)
-- Dependencies: 286
-- Name: workflow_stages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.workflow_stages_id_seq OWNED BY public.workflow_stages.id;


--
-- TOC entry 335 (class 1259 OID 889032)
-- Name: workflows; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.workflows (
    id bigint NOT NULL,
    name character varying(191) NOT NULL,
    status smallint DEFAULT '1'::smallint NOT NULL,
    matter_id bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.workflows OWNER TO postgres;

--
-- TOC entry 334 (class 1259 OID 889031)
-- Name: workflows_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.workflows_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.workflows_id_seq OWNER TO postgres;

--
-- TOC entry 6260 (class 0 OID 0)
-- Dependencies: 334
-- Name: workflows_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.workflows_id_seq OWNED BY public.workflows.id;


--
-- TOC entry 5275 (class 2604 OID 887984)
-- Name: account_client_receipts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.account_client_receipts ALTER COLUMN id SET DEFAULT nextval('public.account_client_receipts_id_seq'::regclass);


--
-- TOC entry 5256 (class 2604 OID 887864)
-- Name: activities_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activities_logs ALTER COLUMN id SET DEFAULT nextval('public.activities_logs_id_seq'::regclass);


--
-- TOC entry 5212 (class 2604 OID 888457)
-- Name: admins id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins ALTER COLUMN id SET DEFAULT nextval('public.admins_id_seq'::regclass);


--
-- TOC entry 5303 (class 2604 OID 888198)
-- Name: anzsco_occupations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.anzsco_occupations ALTER COLUMN id SET DEFAULT nextval('public.anzsco_occupations_id_seq'::regclass);


--
-- TOC entry 5224 (class 2604 OID 887693)
-- Name: appointment_consultants id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_consultants ALTER COLUMN id SET DEFAULT nextval('public.appointment_consultants_id_seq'::regclass);


--
-- TOC entry 5332 (class 2604 OID 888539)
-- Name: appointment_payments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_payments ALTER COLUMN id SET DEFAULT nextval('public.appointment_payments_id_seq'::regclass);


--
-- TOC entry 5240 (class 2604 OID 887784)
-- Name: appointment_sync_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_sync_logs ALTER COLUMN id SET DEFAULT nextval('public.appointment_sync_logs_id_seq'::regclass);


--
-- TOC entry 5227 (class 2604 OID 887713)
-- Name: booking_appointments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments ALTER COLUMN id SET DEFAULT nextval('public.booking_appointments_id_seq'::regclass);


--
-- TOC entry 5265 (class 2604 OID 887920)
-- Name: branches id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches ALTER COLUMN id SET DEFAULT nextval('public.branches_id_seq'::regclass);


--
-- TOC entry 5283 (class 2604 OID 888008)
-- Name: checkin_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.checkin_logs ALTER COLUMN id SET DEFAULT nextval('public.checkin_logs_id_seq'::regclass);


--
-- TOC entry 5364 (class 2604 OID 889569)
-- Name: client_access_grants id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants ALTER COLUMN id SET DEFAULT nextval('public.client_access_grants_id_seq'::regclass);


--
-- TOC entry 5267 (class 2604 OID 888459)
-- Name: client_addresses id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_addresses ALTER COLUMN id SET DEFAULT nextval('public.client_addresses_id_seq'::regclass);


--
-- TOC entry 5337 (class 2604 OID 888583)
-- Name: client_art_references id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references ALTER COLUMN id SET DEFAULT nextval('public.client_art_references_id_seq'::regclass);


--
-- TOC entry 5291 (class 2604 OID 888088)
-- Name: client_contacts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_contacts ALTER COLUMN id SET DEFAULT nextval('public.client_contacts_id_seq'::regclass);


--
-- TOC entry 5297 (class 2604 OID 888129)
-- Name: client_emails id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_emails ALTER COLUMN id SET DEFAULT nextval('public.client_emails_id_seq'::regclass);


--
-- TOC entry 5253 (class 2604 OID 887834)
-- Name: client_experiences id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_experiences ALTER COLUMN id SET DEFAULT nextval('public.client_experiences_id_seq'::regclass);


--
-- TOC entry 5345 (class 2604 OID 888991)
-- Name: client_matter_payment_forms_verifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications ALTER COLUMN id SET DEFAULT nextval('public.client_matter_payment_forms_verifications_id_seq'::regclass);


--
-- TOC entry 5350 (class 2604 OID 889335)
-- Name: client_matter_references id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references ALTER COLUMN id SET DEFAULT nextval('public.client_matter_references_id_seq'::regclass);


--
-- TOC entry 5260 (class 2604 OID 887884)
-- Name: client_matters id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matters ALTER COLUMN id SET DEFAULT nextval('public.client_matters_id_seq'::regclass);


--
-- TOC entry 5270 (class 2604 OID 887952)
-- Name: client_occupations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_occupations ALTER COLUMN id SET DEFAULT nextval('public.client_occupations_id_seq'::regclass);


--
-- TOC entry 5223 (class 2604 OID 887683)
-- Name: client_passport_informations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_passport_informations ALTER COLUMN id SET DEFAULT nextval('public.client_passport_informations_id_seq'::regclass);


--
-- TOC entry 5248 (class 2604 OID 887814)
-- Name: client_qualifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_qualifications ALTER COLUMN id SET DEFAULT nextval('public.client_qualifications_id_seq'::regclass);


--
-- TOC entry 5250 (class 2604 OID 887825)
-- Name: client_spouse_details id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_spouse_details ALTER COLUMN id SET DEFAULT nextval('public.client_spouse_details_id_seq'::regclass);


--
-- TOC entry 5255 (class 2604 OID 887843)
-- Name: client_testscore id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_testscore ALTER COLUMN id SET DEFAULT nextval('public.client_testscore_id_seq'::regclass);


--
-- TOC entry 5284 (class 2604 OID 888458)
-- Name: client_visa_countries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_visa_countries ALTER COLUMN id SET DEFAULT nextval('public.client_visa_countries_id_seq'::regclass);


--
-- TOC entry 5327 (class 2604 OID 888431)
-- Name: clientportal_details_audit id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientportal_details_audit ALTER COLUMN id SET DEFAULT nextval('public.clientportal_details_audit_id_seq'::regclass);


--
-- TOC entry 5330 (class 2604 OID 888511)
-- Name: companies id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies ALTER COLUMN id SET DEFAULT nextval('public.companies_id_seq'::regclass);


--
-- TOC entry 5359 (class 2604 OID 889509)
-- Name: company_directors id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_directors ALTER COLUMN id SET DEFAULT nextval('public.company_directors_id_seq'::regclass);


--
-- TOC entry 5362 (class 2604 OID 889529)
-- Name: company_nominations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_nominations ALTER COLUMN id SET DEFAULT nextval('public.company_nominations_id_seq'::regclass);


--
-- TOC entry 5371 (class 2604 OID 889635)
-- Name: company_sponsorships id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_sponsorships ALTER COLUMN id SET DEFAULT nextval('public.company_sponsorships_id_seq'::regclass);


--
-- TOC entry 5356 (class 2604 OID 889487)
-- Name: company_trading_names id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_trading_names ALTER COLUMN id SET DEFAULT nextval('public.company_trading_names_id_seq'::regclass);


--
-- TOC entry 5286 (class 2604 OID 888032)
-- Name: cp_doc_checklists id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cp_doc_checklists ALTER COLUMN id SET DEFAULT nextval('public.application_document_lists_id_seq'::regclass);


--
-- TOC entry 5287 (class 2604 OID 888040)
-- Name: device_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.device_tokens ALTER COLUMN id SET DEFAULT nextval('public.device_tokens_id_seq'::regclass);


--
-- TOC entry 5318 (class 2604 OID 888327)
-- Name: document_notes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_notes ALTER COLUMN id SET DEFAULT nextval('public.document_notes_id_seq'::regclass);


--
-- TOC entry 5271 (class 2604 OID 887960)
-- Name: documents id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents ALTER COLUMN id SET DEFAULT nextval('public.documents_id_seq'::regclass);


--
-- TOC entry 5323 (class 2604 OID 888386)
-- Name: email_label_email_log id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_label_email_log ALTER COLUMN id SET DEFAULT nextval('public.email_label_mail_report_id_seq'::regclass);


--
-- TOC entry 5319 (class 2604 OID 888353)
-- Name: email_labels id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_labels ALTER COLUMN id SET DEFAULT nextval('public.email_labels_id_seq'::regclass);


--
-- TOC entry 5324 (class 2604 OID 888400)
-- Name: email_log_attachments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_log_attachments ALTER COLUMN id SET DEFAULT nextval('public.mail_report_attachments_id_seq'::regclass);


--
-- TOC entry 5273 (class 2604 OID 887976)
-- Name: email_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_logs ALTER COLUMN id SET DEFAULT nextval('public.mail_reports_id_seq'::regclass);


--
-- TOC entry 5355 (class 2604 OID 889465)
-- Name: email_templates id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_templates ALTER COLUMN id SET DEFAULT nextval('public.email_templates_id_seq'::regclass);


--
-- TOC entry 5299 (class 2604 OID 888150)
-- Name: email_verifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_verifications ALTER COLUMN id SET DEFAULT nextval('public.email_verifications_id_seq'::regclass);


--
-- TOC entry 5272 (class 2604 OID 887968)
-- Name: emails id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.emails ALTER COLUMN id SET DEFAULT nextval('public.emails_id_seq'::regclass);


--
-- TOC entry 5221 (class 2604 OID 887664)
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- TOC entry 5375 (class 2604 OID 889658)
-- Name: front_desk_check_ins id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.front_desk_check_ins ALTER COLUMN id SET DEFAULT nextval('public.front_desk_check_ins_id_seq'::regclass);


--
-- TOC entry 5220 (class 2604 OID 887634)
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- TOC entry 5353 (class 2604 OID 889403)
-- Name: lead_matter_references id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references ALTER COLUMN id SET DEFAULT nextval('public.lead_matter_references_id_seq'::regclass);


--
-- TOC entry 5354 (class 2604 OID 889439)
-- Name: lead_reminders id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_reminders ALTER COLUMN id SET DEFAULT nextval('public.lead_reminders_id_seq'::regclass);


--
-- TOC entry 5352 (class 2604 OID 889376)
-- Name: matter_reminders id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matter_reminders ALTER COLUMN id SET DEFAULT nextval('public.matter_reminders_id_seq'::regclass);


--
-- TOC entry 5281 (class 2604 OID 888000)
-- Name: matters id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matters ALTER COLUMN id SET DEFAULT nextval('public.matters_id_seq'::regclass);


--
-- TOC entry 5346 (class 2604 OID 889012)
-- Name: message_attachments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_attachments ALTER COLUMN id SET DEFAULT nextval('public.message_attachments_id_seq'::regclass);


--
-- TOC entry 5301 (class 2604 OID 888177)
-- Name: message_recipients id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_recipients ALTER COLUMN id SET DEFAULT nextval('public.message_recipients_id_seq'::regclass);


--
-- TOC entry 5269 (class 2604 OID 887944)
-- Name: messages id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.messages ALTER COLUMN id SET DEFAULT nextval('public.messages_id_seq'::regclass);


--
-- TOC entry 5211 (class 2604 OID 888456)
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- TOC entry 5369 (class 2604 OID 889622)
-- Name: nomination_document_types id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nomination_document_types ALTER COLUMN id SET DEFAULT nextval('public.nomination_document_types_id_seq'::regclass);


--
-- TOC entry 5263 (class 2604 OID 887902)
-- Name: notes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notes ALTER COLUMN id SET DEFAULT nextval('public.notes_id_seq'::regclass);


--
-- TOC entry 5261 (class 2604 OID 887893)
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- TOC entry 5293 (class 2604 OID 888098)
-- Name: phone_verifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.phone_verifications ALTER COLUMN id SET DEFAULT nextval('public.phone_verifications_id_seq'::regclass);


--
-- TOC entry 5289 (class 2604 OID 888063)
-- Name: refresh_tokens id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refresh_tokens ALTER COLUMN id SET DEFAULT nextval('public.refresh_tokens_id_seq'::regclass);


--
-- TOC entry 5258 (class 2604 OID 887875)
-- Name: signers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.signers ALTER COLUMN id SET DEFAULT nextval('public.signers_id_seq'::regclass);


--
-- TOC entry 5310 (class 2604 OID 888260)
-- Name: sms_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms_logs ALTER COLUMN id SET DEFAULT nextval('public.sms_logs_id_seq'::regclass);


--
-- TOC entry 5315 (class 2604 OID 888290)
-- Name: sms_templates id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms_templates ALTER COLUMN id SET DEFAULT nextval('public.sms_templates_id_seq'::regclass);


--
-- TOC entry 5340 (class 2604 OID 888634)
-- Name: staff id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff ALTER COLUMN id SET DEFAULT nextval('public.staff_id_seq'::regclass);


--
-- TOC entry 5329 (class 2604 OID 888491)
-- Name: staff_login_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff_login_logs ALTER COLUMN id SET DEFAULT nextval('public.user_logs_id_seq'::regclass);


--
-- TOC entry 5278 (class 2604 OID 887992)
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass);


--
-- TOC entry 5264 (class 2604 OID 887912)
-- Name: teams id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teams ALTER COLUMN id SET DEFAULT nextval('public.teams_id_seq'::regclass);


--
-- TOC entry 5266 (class 2604 OID 887928)
-- Name: user_roles id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_roles ALTER COLUMN id SET DEFAULT nextval('public.user_roles_id_seq'::regclass);


--
-- TOC entry 5285 (class 2604 OID 888024)
-- Name: workflow_stages id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_stages ALTER COLUMN id SET DEFAULT nextval('public.workflow_stages_id_seq'::regclass);


--
-- TOC entry 5348 (class 2604 OID 889035)
-- Name: workflows id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflows ALTER COLUMN id SET DEFAULT nextval('public.workflows_id_seq'::regclass);


--
-- TOC entry 5971 (class 0 OID 887981)
-- Dependencies: 277
-- Data for Name: account_client_receipts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.account_client_receipts (id, created_at, updated_at, void_fee_transfer, voided_at, voided_by, pdf_document_id, client_portal_sent, client_portal_sent_at, client_portal_payment_token, client_portal_payment_type, eftpos_surcharge_amount) FROM stdin;
\.


--
-- TOC entry 5943 (class 0 OID 887861)
-- Dependencies: 249
-- Data for Name: activities_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.activities_logs (id, client_id, description, created_at, updated_at, sms_log_id, activity_type, source) FROM stdin;
\.


--
-- TOC entry 5916 (class 0 OID 887574)
-- Dependencies: 222
-- Data for Name: admins; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.admins (id, role, first_name, last_name, email, password, country, state, city, address, zip, status, service_token, token_generated_at, cp_status, cp_random_code, cp_code_verify, cp_token_generated_at, visa_expiry_verified_at, visa_expiry_verified_by, naati_test, py_test, naati_date, py_date, marital_status, remember_token, created_at, updated_at, australian_study, australian_study_date, specialist_education, specialist_education_date, regional_study, regional_study_date, client_counter, client_id, archived_by, is_company, lead_status, followup_date, google_review_reminder_status, google_review_reminder_snooze_until) FROM stdin;
\.


--
-- TOC entry 5999 (class 0 OID 888195)
-- Dependencies: 305
-- Data for Name: anzsco_occupations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.anzsco_occupations (id, anzsco_code, occupation_title, occupation_title_normalized, skill_level, is_on_mltssl, is_on_stsol, is_on_rol, is_on_csol, assessing_authority, assessment_validity_years, additional_info, alternate_titles, is_active, created_by, updated_by, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5929 (class 0 OID 887690)
-- Dependencies: 235
-- Data for Name: appointment_consultants; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.appointment_consultants (id, name, email, calendar_type, location, specializations, is_active, created_at, updated_at) FROM stdin;
1	Kunal Calendar	kunal@bansalimmigration.com	kunal	melbourne	[]	t	2026-04-03 15:47:41	2026-04-03 15:47:41
\.


--
-- TOC entry 6019 (class 0 OID 888536)
-- Dependencies: 325
-- Data for Name: appointment_payments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.appointment_payments (id, appointment_id, payment_gateway, transaction_id, charge_id, customer_id, payment_method_id, amount, currency, status, error_message, transaction_data, receipt_url, refund_amount, refunded_at, client_ip, user_agent, processed_at, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5933 (class 0 OID 887781)
-- Dependencies: 239
-- Data for Name: appointment_sync_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.appointment_sync_logs (id, sync_type, started_at, completed_at, status, appointments_fetched, appointments_new, appointments_updated, appointments_skipped, appointments_failed, error_message, sync_details, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5931 (class 0 OID 887710)
-- Dependencies: 237
-- Data for Name: booking_appointments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.booking_appointments (id, bansal_appointment_id, order_hash, client_id, consultant_id, assigned_by_admin_id, client_name, client_email, client_phone, client_timezone, appointment_datetime, timeslot_full, duration_minutes, location, inperson_address, meeting_type, preferred_language, service_id, noe_id, enquiry_type, service_type, enquiry_details, status, confirmed_at, completed_at, cancelled_at, cancellation_reason, is_paid, amount, discount_amount, final_amount, promo_code, payment_status, payment_method, paid_at, admin_notes, confirmation_email_sent, confirmation_email_sent_at, reminder_sms_sent, reminder_sms_sent_at, synced_from_bansal_at, last_synced_at, sync_status, sync_error, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5955 (class 0 OID 887917)
-- Dependencies: 261
-- Data for Name: branches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.branches (id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5919 (class 0 OID 887612)
-- Dependencies: 225
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- TOC entry 5920 (class 0 OID 887622)
-- Dependencies: 226
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- TOC entry 5977 (class 0 OID 888005)
-- Dependencies: 283
-- Data for Name: checkin_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.checkin_logs (id, created_at, updated_at, walk_in_phone, walk_in_email) FROM stdin;
\.


--
-- TOC entry 6047 (class 0 OID 889566)
-- Dependencies: 353
-- Data for Name: client_access_grants; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_access_grants (id, staff_id, admin_id, record_type, grant_type, access_type, status, quick_reason_code, requester_note, office_id, office_label_snapshot, team_id, team_label_snapshot, requested_at, approved_at, approved_by_staff_id, starts_at, ends_at, revoked_at, revoke_reason, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5959 (class 0 OID 887933)
-- Dependencies: 265
-- Data for Name: client_addresses; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_addresses (id, created_at, updated_at, address_line_1, address_line_2, suburb, country, zip) FROM stdin;
\.


--
-- TOC entry 6021 (class 0 OID 888580)
-- Dependencies: 327
-- Data for Name: client_art_references; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_art_references (id, client_id, client_matter_id, submission_last_date, status_of_file, hearing_time, member_name, outcome, comments, created_by, updated_by, created_at, updated_at, is_pinned) FROM stdin;
\.


--
-- TOC entry 5989 (class 0 OID 888085)
-- Dependencies: 295
-- Data for Name: client_contacts; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_contacts (id, admin_id, client_id, contact_type, country_code, phone, created_at, updated_at, is_verified, verified_at, verified_by) FROM stdin;
\.


--
-- TOC entry 5993 (class 0 OID 888126)
-- Dependencies: 299
-- Data for Name: client_emails; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_emails (id, admin_id, client_id, email_type, email, created_at, updated_at, is_verified, verified_at, verified_by, verification_token, token_expires_at, verification_sent_at) FROM stdin;
\.


--
-- TOC entry 5939 (class 0 OID 887831)
-- Dependencies: 245
-- Data for Name: client_experiences; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_experiences (id, client_id, job_country, job_type, created_at, updated_at, fte_multiplier) FROM stdin;
\.


--
-- TOC entry 6025 (class 0 OID 888988)
-- Dependencies: 331
-- Data for Name: client_matter_payment_forms_verifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_matter_payment_forms_verifications (id, client_matter_id, verified_by, verified_at, note, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6031 (class 0 OID 889332)
-- Dependencies: 337
-- Data for Name: client_matter_references; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_matter_references (id, type, client_id, client_matter_id, current_status, payment_display_note, institute_override, visa_category_override, comments, checklist_sent_at, is_pinned, created_by, updated_by, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5947 (class 0 OID 887881)
-- Dependencies: 253
-- Data for Name: client_matters; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_matters (id, client_id, matter_status, created_at, updated_at, office_id, tr_checklist_status, visitor_checklist_status, student_checklist_status, pr_checklist_status, employer_sponsored_checklist_status, deadline, decision_outcome, decision_note, workflow_id, partner_checklist_status, parents_checklist_status) FROM stdin;
\.


--
-- TOC entry 5963 (class 0 OID 887949)
-- Dependencies: 269
-- Data for Name: client_occupations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_occupations (id, created_at, updated_at, anzsco_occupation_id) FROM stdin;
\.


--
-- TOC entry 5927 (class 0 OID 887680)
-- Dependencies: 233
-- Data for Name: client_passport_informations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_passport_informations (id, client_id, admin_id, passport, passport_number, passport_country, passport_issue_date, passport_expiry_date, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5935 (class 0 OID 887811)
-- Dependencies: 241
-- Data for Name: client_qualifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_qualifications (id, client_id, country, relevant_qualification, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5937 (class 0 OID 887822)
-- Dependencies: 243
-- Data for Name: client_spouse_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_spouse_details (id, client_id, spouse_assessment_date, created_at, updated_at, is_citizen, has_pr, dob, related_client_id) FROM stdin;
\.


--
-- TOC entry 5941 (class 0 OID 887840)
-- Dependencies: 247
-- Data for Name: client_testscore; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_testscore (id, overall_score, created_at, updated_at, proficiency_level, proficiency_points) FROM stdin;
\.


--
-- TOC entry 5979 (class 0 OID 888013)
-- Dependencies: 285
-- Data for Name: client_visa_countries; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.client_visa_countries (id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6013 (class 0 OID 888428)
-- Dependencies: 319
-- Data for Name: clientportal_details_audit; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.clientportal_details_audit (id, client_id, meta_key, old_value, new_value, meta_order, meta_type, action, updated_by, updated_at) FROM stdin;
\.


--
-- TOC entry 6017 (class 0 OID 888508)
-- Dependencies: 323
-- Data for Name: companies; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.companies (id, admin_id, company_name, trading_name, "ABN_number", "ACN", company_type, company_website, contact_person_id, contact_person_position, created_at, updated_at, has_trading_name, trust_name, trust_abn, trustee_name, trustee_details, sponsorship_type, sponsorship_status, sponsorship_start_date, sponsorship_end_date, trn, regional_sponsorship, adverse_information, previous_sponsorship_notes, annual_turnover, wages_expenditure, workforce_australian_citizens, workforce_permanent_residents, workforce_temp_visa_holders, workforce_total, business_operating_since, main_business_activity, lmt_required, lmt_start_date, lmt_end_date, lmt_notes, training_position_title, trainer_name, workforce_foreign_494, workforce_foreign_other_temp_activity, workforce_foreign_overseas_students, workforce_foreign_working_holiday, workforce_foreign_other) FROM stdin;
\.


--
-- TOC entry 6043 (class 0 OID 889506)
-- Dependencies: 349
-- Data for Name: company_directors; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.company_directors (id, company_id, director_name, director_dob, director_role, is_primary, sort_order, created_at, updated_at, director_client_id) FROM stdin;
\.


--
-- TOC entry 6045 (class 0 OID 889526)
-- Dependencies: 351
-- Data for Name: company_nominations; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.company_nominations (id, company_id, position_title, anzsco_code, position_description, salary, duration, nominated_client_id, nominated_person_name, trn, status, nomination_date, expiry_date, sort_order, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6051 (class 0 OID 889632)
-- Dependencies: 357
-- Data for Name: company_sponsorships; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.company_sponsorships (id, company_id, sponsorship_type, sponsorship_status, sponsorship_start_date, sponsorship_end_date, trn, regional_sponsorship, adverse_information, previous_sponsorship_notes, sort_order, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6041 (class 0 OID 889484)
-- Dependencies: 347
-- Data for Name: company_trading_names; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.company_trading_names (id, company_id, trading_name, is_primary, sort_order, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5983 (class 0 OID 888029)
-- Dependencies: 289
-- Data for Name: cp_doc_checklists; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cp_doc_checklists (id, created_at, updated_at, wf_stage, wf_stage_id) FROM stdin;
\.


--
-- TOC entry 5985 (class 0 OID 888037)
-- Dependencies: 291
-- Data for Name: device_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.device_tokens (id, user_id, device_token, device_name, device_type, app_version, os_version, is_active, last_used_at, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6005 (class 0 OID 888324)
-- Dependencies: 311
-- Data for Name: document_notes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.document_notes (id, document_id, created_by, action_type, note, metadata, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5965 (class 0 OID 887957)
-- Dependencies: 271
-- Data for Name: documents; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.documents (id, created_at, updated_at, created_by, office_id, form956_id, cp_list_id, cp_rejection_reason, cp_doc_status, lead_id) FROM stdin;
\.


--
-- TOC entry 6009 (class 0 OID 888383)
-- Dependencies: 315
-- Data for Name: email_label_email_log; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_label_email_log (id, email_log_id, email_label_id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6007 (class 0 OID 888350)
-- Dependencies: 313
-- Data for Name: email_labels; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_labels (id, user_id, name, color, type, icon, description, is_active, created_at, updated_at) FROM stdin;
1	\N	Inbox	#3B82F6	system	fas fa-inbox	Received emails	t	2026-04-03 15:47:30	2026-04-03 15:47:30
2	\N	Sent	#10B981	system	fas fa-paper-plane	Sent emails	t	2026-04-03 15:47:30	2026-04-03 15:47:30
3	\N	Important	#EF4444	system	fas fa-star	Important emails	t	2026-04-03 15:47:30	2026-04-03 15:47:30
4	\N	Follow Up	#F59E0B	system	fas fa-flag	Emails requiring follow up	t	2026-04-03 15:47:30	2026-04-03 15:47:30
\.


--
-- TOC entry 6011 (class 0 OID 888397)
-- Dependencies: 317
-- Data for Name: email_log_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_log_attachments (id, email_log_id, filename, display_name, content_type, file_path, s3_key, file_size, content_id, is_inline, description, headers, extension, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5969 (class 0 OID 887973)
-- Dependencies: 275
-- Data for Name: email_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_logs (id, created_at, updated_at, python_analysis, python_rendering, sentiment, language, enhanced_html, rendered_html, text_preview, security_issues, thread_info, message_id, thread_id, received_date, processed_at, last_accessed_at, file_hash) FROM stdin;
\.


--
-- TOC entry 6039 (class 0 OID 889462)
-- Dependencies: 345
-- Data for Name: email_templates; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_templates (id, type, matter_id, name, subject, description, created_at, updated_at, alias) FROM stdin;
\.


--
-- TOC entry 5995 (class 0 OID 888147)
-- Dependencies: 301
-- Data for Name: email_verifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.email_verifications (id, client_email_id, client_id, email, verification_token, is_verified, verified_at, verified_by, token_sent_at, token_expires_at, ip_address, user_agent, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5967 (class 0 OID 887965)
-- Dependencies: 273
-- Data for Name: emails; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.emails (id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5925 (class 0 OID 887661)
-- Dependencies: 231
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- TOC entry 6053 (class 0 OID 889655)
-- Dependencies: 359
-- Data for Name: front_desk_check_ins; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.front_desk_check_ins (id, admin_id, phone_normalized, email, client_id, lead_id, appointment_id, claimed_appointment, visit_reason, visit_notes, notified_staff_id, notified_at, metadata, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5923 (class 0 OID 887646)
-- Dependencies: 229
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- TOC entry 5922 (class 0 OID 887631)
-- Dependencies: 228
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- TOC entry 6035 (class 0 OID 889400)
-- Dependencies: 341
-- Data for Name: lead_matter_references; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.lead_matter_references (id, type, lead_id, matter_id, checklist_sent_at, created_by, updated_by, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6037 (class 0 OID 889436)
-- Dependencies: 343
-- Data for Name: lead_reminders; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.lead_reminders (id, visa_type, lead_id, type, reminded_at, reminded_by, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6033 (class 0 OID 889373)
-- Dependencies: 339
-- Data for Name: matter_reminders; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.matter_reminders (id, visa_type, client_matter_id, type, reminded_at, reminded_by, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5975 (class 0 OID 887997)
-- Dependencies: 281
-- Data for Name: matters; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.matters (id, created_at, updated_at, is_for_company, workflow_id) FROM stdin;
\.


--
-- TOC entry 6027 (class 0 OID 889009)
-- Dependencies: 333
-- Data for Name: message_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.message_attachments (id, message_id, filename, original_filename, path, mime_type, type, size, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5997 (class 0 OID 888174)
-- Dependencies: 303
-- Data for Name: message_recipients; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.message_recipients (id, message_id, recipient_id, recipient, is_read, read_at, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5961 (class 0 OID 887941)
-- Dependencies: 267
-- Data for Name: messages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.messages (id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5914 (class 0 OID 887564)
-- Dependencies: 220
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: postgres
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
199	2026_04_03_140000_update_sms_templates_bansal_lawyers_brand	2
\.


--
-- TOC entry 6049 (class 0 OID 889619)
-- Dependencies: 355
-- Data for Name: nomination_document_types; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nomination_document_types (id, title, status, client_id, client_matter_id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5951 (class 0 OID 887899)
-- Dependencies: 257
-- Data for Name: notes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notes (id, type, status, assigned_to, is_action, task_group, action_date, client_id, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5949 (class 0 OID 887890)
-- Dependencies: 255
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, receiver_id, notification_type, receiver_status, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5917 (class 0 OID 887593)
-- Dependencies: 223
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- TOC entry 5991 (class 0 OID 888095)
-- Dependencies: 297
-- Data for Name: phone_verifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.phone_verifications (id, client_contact_id, client_id, phone, country_code, otp_code, is_verified, verified_at, verified_by, otp_sent_at, otp_expires_at, attempts, max_attempts, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5987 (class 0 OID 888060)
-- Dependencies: 293
-- Data for Name: refresh_tokens; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.refresh_tokens (id, user_id, token, device_name, expires_at, is_revoked, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5918 (class 0 OID 887600)
-- Dependencies: 224
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
\.


--
-- TOC entry 5945 (class 0 OID 887872)
-- Dependencies: 251
-- Data for Name: signers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.signers (id, status, reminder_count, created_at, updated_at, email_template, email_subject, email_message, from_email, cancelled_at) FROM stdin;
\.


--
-- TOC entry 6001 (class 0 OID 888257)
-- Dependencies: 307
-- Data for Name: sms_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sms_logs (id, client_id, client_contact_id, sender_id, recipient_phone, country_code, formatted_phone, message_content, message_type, template_id, provider, provider_message_id, status, error_message, cost, sent_at, delivered_at, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 6003 (class 0 OID 888287)
-- Dependencies: 309
-- Data for Name: sms_templates; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sms_templates (id, title, message, variables, category, alias, is_active, usage_count, created_by, created_at, updated_at, description) FROM stdin;
1	Appointment Reminder	Hi {first_name}, this is a reminder for your appointment on {appointment_date} at {appointment_time}. Call {office_phone} if you need to reschedule.	first_name,appointment_date,appointment_time,office_phone	reminder	appointment_reminder	t	0	\N	2026-04-03 15:47:30	2026-04-03 15:47:30	\N
2	Document Upload Request	Hi {first_name}, please upload the requested documents for matter {matter_number}. Login to your client portal or contact {staff_name} at {office_phone}.	first_name,matter_number,staff_name,office_phone	notification	document_request	t	0	\N	2026-04-03 15:47:30	2026-04-03 15:47:30	\N
5	Payment Reminder	Hi {first_name}, this is a reminder about your pending payment for invoice #{invoice_number}. Please contact us at {office_phone} or login to your portal to make payment.	first_name,invoice_number,office_phone	reminder	payment_reminder	t	0	\N	2026-04-03 15:47:30	2026-04-03 15:47:30	\N
4	General Follow-up	Hi {client_name}, {staff_name} from BANSAL Lawyers. Please call us at {office_phone} regarding your matter {matter_number}.	client_name,staff_name,office_phone,matter_number	manual	general_followup	t	0	\N	2026-04-03 15:47:30	2026-04-03 16:04:34	\N
3	Phone Verification Code	BANSAL LAWYERS: Your phone verification code is {verification_code}. Please provide this code to our staff to verify your phone number. This code expires in {expiry_minutes} minutes.	verification_code,expiry_minutes	verification	phone_verification	t	0	\N	2026-04-03 15:47:30	2026-04-03 16:04:34	\N
6	Not Picked Call	Hi {first_name},\n\nWe tried reaching you but couldn't connect. Please call us at {office_phone} or let us know a suitable time.\n\nPlease do not reply via SMS.\n\nBANSAL Lawyers	first_name,office_phone	notification	not_picked_call	t	0	\N	2026-04-03 15:47:41	2026-04-03 16:04:34	\N
7	Booking Reminder — In Person	BANSAL LAWYERS: Reminder - You have a scheduled In-Person appointment tomorrow at {timeslot_full} at our {location} office. Please be on time. If you need to reschedule, call us at {office_phone}.	timeslot_full,location,office_phone	reminder	booking_reminder_in_person	t	0	\N	2026-04-03 15:47:41	2026-04-03 16:04:34	\N
8	Booking Reminder — Phone	BANSAL LAWYERS: Reminder - You have a scheduled Phone appointment tomorrow at {timeslot_full} . Please be on time. If you need to reschedule, call us at {office_phone}.	timeslot_full,office_phone	reminder	booking_reminder_phone	t	0	\N	2026-04-03 15:47:41	2026-04-03 16:04:34	\N
9	Booking Reminder — Video	BANSAL LAWYERS: Reminder - You have a scheduled Video Call appointment tomorrow at {timeslot_full} . Please be on time. If you need to reschedule, call us at {office_phone}.	timeslot_full,office_phone	reminder	booking_reminder_video	t	0	\N	2026-04-03 15:47:41	2026-04-03 16:04:34	\N
10	Booking Reminder — Default	BANSAL LAWYERS: Reminder - You have a scheduled appointment tomorrow at {timeslot_full} at our {location} office. Please be on time. If you need to reschedule, call us at {office_phone}.	timeslot_full,location,office_phone	reminder	booking_reminder_default	t	0	\N	2026-04-03 15:47:41	2026-04-03 16:04:34	\N
\.


--
-- TOC entry 6023 (class 0 OID 888631)
-- Dependencies: 329
-- Data for Name: staff; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.staff (id, first_name, last_name, email, password, country_code, phone, status, role, "position", team, permission, office_id, show_dashboard_per, time_zone, is_migration_agent, marn_number, legal_practitioner_number, company_name, company_website, business_address, business_phone, business_mobile, business_email, tax_number, remember_token, created_at, updated_at, sheet_access, quick_access_enabled, grant_super_admin_access) FROM stdin;
1	Admin	One	admin1@gmail.com	$2y$10$Xpc/cHHlhgJbiTWiKPKdW.7ejtQnLdZN6lnovVBa7As0ONr57T1.6	\N	0000000000	1	1	\N	\N	\N	\N	0	\N	0	\N	\N	\N	\N	\N	\N	\N	\N	\N	\N	2026-04-03 16:14:58	2026-04-03 16:14:58	\N	f	\N
\.


--
-- TOC entry 6015 (class 0 OID 888488)
-- Dependencies: 321
-- Data for Name: staff_login_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.staff_login_logs (id, level, user_id, ip_address, user_agent, message, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5973 (class 0 OID 887989)
-- Dependencies: 279
-- Data for Name: tags; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tags (id, created_at, updated_at, tag_type, is_hidden) FROM stdin;
\.


--
-- TOC entry 5953 (class 0 OID 887909)
-- Dependencies: 259
-- Data for Name: teams; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.teams (id, name, color, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 5957 (class 0 OID 887925)
-- Dependencies: 263
-- Data for Name: user_roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.user_roles (id, created_at, updated_at) FROM stdin;
1	2026-04-03 16:14:58	2026-04-03 16:14:58
\.


--
-- TOC entry 5981 (class 0 OID 888021)
-- Dependencies: 287
-- Data for Name: workflow_stages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.workflow_stages (id, name, created_at, updated_at, sort_order, workflow_id) FROM stdin;
\.


--
-- TOC entry 6029 (class 0 OID 889032)
-- Dependencies: 335
-- Data for Name: workflows; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.workflows (id, name, status, matter_id, created_at, updated_at) FROM stdin;
1	General	1	\N	2026-04-03 15:47:40	2026-04-03 15:47:40
\.


--
-- TOC entry 6261 (class 0 OID 0)
-- Dependencies: 276
-- Name: account_client_receipts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.account_client_receipts_id_seq', 1, false);


--
-- TOC entry 6262 (class 0 OID 0)
-- Dependencies: 248
-- Name: activities_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.activities_logs_id_seq', 1, false);


--
-- TOC entry 6263 (class 0 OID 0)
-- Dependencies: 221
-- Name: admins_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admins_id_seq', 1, false);


--
-- TOC entry 6264 (class 0 OID 0)
-- Dependencies: 304
-- Name: anzsco_occupations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.anzsco_occupations_id_seq', 1, false);


--
-- TOC entry 6265 (class 0 OID 0)
-- Dependencies: 288
-- Name: application_document_lists_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.application_document_lists_id_seq', 1, false);


--
-- TOC entry 6266 (class 0 OID 0)
-- Dependencies: 234
-- Name: appointment_consultants_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.appointment_consultants_id_seq', 1, true);


--
-- TOC entry 6267 (class 0 OID 0)
-- Dependencies: 324
-- Name: appointment_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.appointment_payments_id_seq', 1, false);


--
-- TOC entry 6268 (class 0 OID 0)
-- Dependencies: 238
-- Name: appointment_sync_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.appointment_sync_logs_id_seq', 1, false);


--
-- TOC entry 6269 (class 0 OID 0)
-- Dependencies: 236
-- Name: booking_appointments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.booking_appointments_id_seq', 1, false);


--
-- TOC entry 6270 (class 0 OID 0)
-- Dependencies: 260
-- Name: branches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.branches_id_seq', 1, false);


--
-- TOC entry 6271 (class 0 OID 0)
-- Dependencies: 282
-- Name: checkin_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.checkin_logs_id_seq', 1, false);


--
-- TOC entry 6272 (class 0 OID 0)
-- Dependencies: 352
-- Name: client_access_grants_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_access_grants_id_seq', 1, false);


--
-- TOC entry 6273 (class 0 OID 0)
-- Dependencies: 264
-- Name: client_addresses_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_addresses_id_seq', 1, false);


--
-- TOC entry 6274 (class 0 OID 0)
-- Dependencies: 326
-- Name: client_art_references_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_art_references_id_seq', 1, false);


--
-- TOC entry 6275 (class 0 OID 0)
-- Dependencies: 294
-- Name: client_contacts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_contacts_id_seq', 1, false);


--
-- TOC entry 6276 (class 0 OID 0)
-- Dependencies: 298
-- Name: client_emails_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_emails_id_seq', 1, false);


--
-- TOC entry 6277 (class 0 OID 0)
-- Dependencies: 244
-- Name: client_experiences_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_experiences_id_seq', 1, false);


--
-- TOC entry 6278 (class 0 OID 0)
-- Dependencies: 330
-- Name: client_matter_payment_forms_verifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_matter_payment_forms_verifications_id_seq', 1, false);


--
-- TOC entry 6279 (class 0 OID 0)
-- Dependencies: 336
-- Name: client_matter_references_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_matter_references_id_seq', 1, false);


--
-- TOC entry 6280 (class 0 OID 0)
-- Dependencies: 252
-- Name: client_matters_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_matters_id_seq', 1, false);


--
-- TOC entry 6281 (class 0 OID 0)
-- Dependencies: 268
-- Name: client_occupations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_occupations_id_seq', 1, false);


--
-- TOC entry 6282 (class 0 OID 0)
-- Dependencies: 232
-- Name: client_passport_informations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_passport_informations_id_seq', 1, false);


--
-- TOC entry 6283 (class 0 OID 0)
-- Dependencies: 240
-- Name: client_qualifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_qualifications_id_seq', 1, false);


--
-- TOC entry 6284 (class 0 OID 0)
-- Dependencies: 242
-- Name: client_spouse_details_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_spouse_details_id_seq', 1, false);


--
-- TOC entry 6285 (class 0 OID 0)
-- Dependencies: 246
-- Name: client_testscore_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_testscore_id_seq', 1, false);


--
-- TOC entry 6286 (class 0 OID 0)
-- Dependencies: 284
-- Name: client_visa_countries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.client_visa_countries_id_seq', 1, false);


--
-- TOC entry 6287 (class 0 OID 0)
-- Dependencies: 318
-- Name: clientportal_details_audit_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.clientportal_details_audit_id_seq', 1, false);


--
-- TOC entry 6288 (class 0 OID 0)
-- Dependencies: 322
-- Name: companies_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.companies_id_seq', 1, false);


--
-- TOC entry 6289 (class 0 OID 0)
-- Dependencies: 348
-- Name: company_directors_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.company_directors_id_seq', 1, false);


--
-- TOC entry 6290 (class 0 OID 0)
-- Dependencies: 350
-- Name: company_nominations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.company_nominations_id_seq', 1, false);


--
-- TOC entry 6291 (class 0 OID 0)
-- Dependencies: 356
-- Name: company_sponsorships_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.company_sponsorships_id_seq', 1, false);


--
-- TOC entry 6292 (class 0 OID 0)
-- Dependencies: 346
-- Name: company_trading_names_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.company_trading_names_id_seq', 1, false);


--
-- TOC entry 6293 (class 0 OID 0)
-- Dependencies: 290
-- Name: device_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.device_tokens_id_seq', 1, false);


--
-- TOC entry 6294 (class 0 OID 0)
-- Dependencies: 310
-- Name: document_notes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.document_notes_id_seq', 1, false);


--
-- TOC entry 6295 (class 0 OID 0)
-- Dependencies: 270
-- Name: documents_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.documents_id_seq', 1, false);


--
-- TOC entry 6296 (class 0 OID 0)
-- Dependencies: 314
-- Name: email_label_mail_report_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.email_label_mail_report_id_seq', 1, false);


--
-- TOC entry 6297 (class 0 OID 0)
-- Dependencies: 312
-- Name: email_labels_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.email_labels_id_seq', 4, true);


--
-- TOC entry 6298 (class 0 OID 0)
-- Dependencies: 344
-- Name: email_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.email_templates_id_seq', 1, false);


--
-- TOC entry 6299 (class 0 OID 0)
-- Dependencies: 300
-- Name: email_verifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.email_verifications_id_seq', 1, false);


--
-- TOC entry 6300 (class 0 OID 0)
-- Dependencies: 272
-- Name: emails_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.emails_id_seq', 1, false);


--
-- TOC entry 6301 (class 0 OID 0)
-- Dependencies: 230
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- TOC entry 6302 (class 0 OID 0)
-- Dependencies: 358
-- Name: front_desk_check_ins_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.front_desk_check_ins_id_seq', 1, false);


--
-- TOC entry 6303 (class 0 OID 0)
-- Dependencies: 227
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- TOC entry 6304 (class 0 OID 0)
-- Dependencies: 340
-- Name: lead_matter_references_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.lead_matter_references_id_seq', 1, false);


--
-- TOC entry 6305 (class 0 OID 0)
-- Dependencies: 342
-- Name: lead_reminders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.lead_reminders_id_seq', 1, false);


--
-- TOC entry 6306 (class 0 OID 0)
-- Dependencies: 316
-- Name: mail_report_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mail_report_attachments_id_seq', 1, false);


--
-- TOC entry 6307 (class 0 OID 0)
-- Dependencies: 274
-- Name: mail_reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.mail_reports_id_seq', 1, false);


--
-- TOC entry 6308 (class 0 OID 0)
-- Dependencies: 338
-- Name: matter_reminders_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.matter_reminders_id_seq', 1, false);


--
-- TOC entry 6309 (class 0 OID 0)
-- Dependencies: 280
-- Name: matters_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.matters_id_seq', 1, false);


--
-- TOC entry 6310 (class 0 OID 0)
-- Dependencies: 332
-- Name: message_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.message_attachments_id_seq', 1, false);


--
-- TOC entry 6311 (class 0 OID 0)
-- Dependencies: 302
-- Name: message_recipients_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.message_recipients_id_seq', 1, false);


--
-- TOC entry 6312 (class 0 OID 0)
-- Dependencies: 266
-- Name: messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.messages_id_seq', 1, false);


--
-- TOC entry 6313 (class 0 OID 0)
-- Dependencies: 219
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.migrations_id_seq', 199, true);


--
-- TOC entry 6314 (class 0 OID 0)
-- Dependencies: 354
-- Name: nomination_document_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nomination_document_types_id_seq', 1, false);


--
-- TOC entry 6315 (class 0 OID 0)
-- Dependencies: 256
-- Name: notes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notes_id_seq', 1, false);


--
-- TOC entry 6316 (class 0 OID 0)
-- Dependencies: 254
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 1, false);


--
-- TOC entry 6317 (class 0 OID 0)
-- Dependencies: 296
-- Name: phone_verifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.phone_verifications_id_seq', 1, false);


--
-- TOC entry 6318 (class 0 OID 0)
-- Dependencies: 292
-- Name: refresh_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.refresh_tokens_id_seq', 1, false);


--
-- TOC entry 6319 (class 0 OID 0)
-- Dependencies: 250
-- Name: signers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.signers_id_seq', 1, false);


--
-- TOC entry 6320 (class 0 OID 0)
-- Dependencies: 306
-- Name: sms_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sms_logs_id_seq', 1, false);


--
-- TOC entry 6321 (class 0 OID 0)
-- Dependencies: 308
-- Name: sms_templates_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sms_templates_id_seq', 10, true);


--
-- TOC entry 6322 (class 0 OID 0)
-- Dependencies: 328
-- Name: staff_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.staff_id_seq', 1, true);


--
-- TOC entry 6323 (class 0 OID 0)
-- Dependencies: 278
-- Name: tags_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tags_id_seq', 1, false);


--
-- TOC entry 6324 (class 0 OID 0)
-- Dependencies: 258
-- Name: teams_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.teams_id_seq', 1, false);


--
-- TOC entry 6325 (class 0 OID 0)
-- Dependencies: 320
-- Name: user_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_logs_id_seq', 1, false);


--
-- TOC entry 6326 (class 0 OID 0)
-- Dependencies: 262
-- Name: user_roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_roles_id_seq', 1, false);


--
-- TOC entry 6327 (class 0 OID 0)
-- Dependencies: 286
-- Name: workflow_stages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.workflow_stages_id_seq', 1, false);


--
-- TOC entry 6328 (class 0 OID 0)
-- Dependencies: 334
-- Name: workflows_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.workflows_id_seq', 1, true);


--
-- TOC entry 5507 (class 2606 OID 887987)
-- Name: account_client_receipts account_client_receipts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.account_client_receipts
    ADD CONSTRAINT account_client_receipts_pkey PRIMARY KEY (id);


--
-- TOC entry 5462 (class 2606 OID 887869)
-- Name: activities_logs activities_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.activities_logs
    ADD CONSTRAINT activities_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 5395 (class 2606 OID 887592)
-- Name: admins admins_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_email_unique UNIQUE (email);


--
-- TOC entry 5397 (class 2606 OID 887590)
-- Name: admins admins_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id);


--
-- TOC entry 5566 (class 2606 OID 888221)
-- Name: anzsco_occupations anzsco_occupations_anzsco_code_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.anzsco_occupations
    ADD CONSTRAINT anzsco_occupations_anzsco_code_unique UNIQUE (anzsco_code);


--
-- TOC entry 5573 (class 2606 OID 888217)
-- Name: anzsco_occupations anzsco_occupations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.anzsco_occupations
    ADD CONSTRAINT anzsco_occupations_pkey PRIMARY KEY (id);


--
-- TOC entry 5521 (class 2606 OID 888035)
-- Name: cp_doc_checklists application_document_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cp_doc_checklists
    ADD CONSTRAINT application_document_lists_pkey PRIMARY KEY (id);


--
-- TOC entry 5423 (class 2606 OID 887706)
-- Name: appointment_consultants appointment_consultants_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_consultants
    ADD CONSTRAINT appointment_consultants_pkey PRIMARY KEY (id);


--
-- TOC entry 5635 (class 2606 OID 888556)
-- Name: appointment_payments appointment_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_payments
    ADD CONSTRAINT appointment_payments_pkey PRIMARY KEY (id);


--
-- TOC entry 5439 (class 2606 OID 887806)
-- Name: appointment_sync_logs appointment_sync_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.appointment_sync_logs
    ADD CONSTRAINT appointment_sync_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 5428 (class 2606 OID 887779)
-- Name: booking_appointments booking_appointments_bansal_appointment_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_bansal_appointment_id_unique UNIQUE (bansal_appointment_id);


--
-- TOC entry 5434 (class 2606 OID 887754)
-- Name: booking_appointments booking_appointments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_pkey PRIMARY KEY (id);


--
-- TOC entry 5484 (class 2606 OID 887923)
-- Name: branches branches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.branches
    ADD CONSTRAINT branches_pkey PRIMARY KEY (id);


--
-- TOC entry 5408 (class 2606 OID 887629)
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- TOC entry 5406 (class 2606 OID 887621)
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- TOC entry 5515 (class 2606 OID 888011)
-- Name: checkin_logs checkin_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.checkin_logs
    ADD CONSTRAINT checkin_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 5700 (class 2606 OID 889587)
-- Name: client_access_grants client_access_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_pkey PRIMARY KEY (id);


--
-- TOC entry 5489 (class 2606 OID 887939)
-- Name: client_addresses client_addresses_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_addresses
    ADD CONSTRAINT client_addresses_pkey PRIMARY KEY (id);


--
-- TOC entry 5642 (class 2606 OID 888594)
-- Name: client_art_references client_art_references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_pkey PRIMARY KEY (id);


--
-- TOC entry 5539 (class 2606 OID 888091)
-- Name: client_contacts client_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_contacts
    ADD CONSTRAINT client_contacts_pkey PRIMARY KEY (id);


--
-- TOC entry 5550 (class 2606 OID 888132)
-- Name: client_emails client_emails_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_pkey PRIMARY KEY (id);


--
-- TOC entry 5453 (class 2606 OID 887837)
-- Name: client_experiences client_experiences_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_experiences
    ADD CONSTRAINT client_experiences_pkey PRIMARY KEY (id);


--
-- TOC entry 5652 (class 2606 OID 888999)
-- Name: client_matter_payment_forms_verifications client_matter_payment_forms_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications
    ADD CONSTRAINT client_matter_payment_forms_verifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5659 (class 2606 OID 889367)
-- Name: client_matter_references client_matter_ref_type_client_matter_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_ref_type_client_matter_unique UNIQUE (type, client_id, client_matter_id);


--
-- TOC entry 5664 (class 2606 OID 889345)
-- Name: client_matter_references client_matter_references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_pkey PRIMARY KEY (id);


--
-- TOC entry 5468 (class 2606 OID 887887)
-- Name: client_matters client_matters_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matters
    ADD CONSTRAINT client_matters_pkey PRIMARY KEY (id);


--
-- TOC entry 5494 (class 2606 OID 887955)
-- Name: client_occupations client_occupations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_occupations
    ADD CONSTRAINT client_occupations_pkey PRIMARY KEY (id);


--
-- TOC entry 5421 (class 2606 OID 887686)
-- Name: client_passport_informations client_passport_informations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_passport_informations
    ADD CONSTRAINT client_passport_informations_pkey PRIMARY KEY (id);


--
-- TOC entry 5445 (class 2606 OID 887819)
-- Name: client_qualifications client_qualifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_qualifications
    ADD CONSTRAINT client_qualifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5449 (class 2606 OID 887828)
-- Name: client_spouse_details client_spouse_details_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_spouse_details
    ADD CONSTRAINT client_spouse_details_pkey PRIMARY KEY (id);


--
-- TOC entry 5456 (class 2606 OID 887846)
-- Name: client_testscore client_testscore_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_testscore
    ADD CONSTRAINT client_testscore_pkey PRIMARY KEY (id);


--
-- TOC entry 5517 (class 2606 OID 888019)
-- Name: client_visa_countries client_visa_countries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_visa_countries
    ADD CONSTRAINT client_visa_countries_pkey PRIMARY KEY (id);


--
-- TOC entry 5614 (class 2606 OID 888440)
-- Name: clientportal_details_audit clientportal_details_audit_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientportal_details_audit
    ADD CONSTRAINT clientportal_details_audit_pkey PRIMARY KEY (id);


--
-- TOC entry 5623 (class 2606 OID 888523)
-- Name: companies companies_admin_id_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_admin_id_unique UNIQUE (admin_id);


--
-- TOC entry 5627 (class 2606 OID 888518)
-- Name: companies companies_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_pkey PRIMARY KEY (id);


--
-- TOC entry 5695 (class 2606 OID 889518)
-- Name: company_directors company_directors_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_pkey PRIMARY KEY (id);


--
-- TOC entry 5698 (class 2606 OID 889537)
-- Name: company_nominations company_nominations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_pkey PRIMARY KEY (id);


--
-- TOC entry 5711 (class 2606 OID 889647)
-- Name: company_sponsorships company_sponsorships_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_sponsorships
    ADD CONSTRAINT company_sponsorships_pkey PRIMARY KEY (id);


--
-- TOC entry 5692 (class 2606 OID 889496)
-- Name: company_trading_names company_trading_names_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_trading_names
    ADD CONSTRAINT company_trading_names_pkey PRIMARY KEY (id);


--
-- TOC entry 5524 (class 2606 OID 888058)
-- Name: device_tokens device_tokens_device_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_device_token_unique UNIQUE (device_token);


--
-- TOC entry 5526 (class 2606 OID 888049)
-- Name: device_tokens device_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_pkey PRIMARY KEY (id);


--
-- TOC entry 5596 (class 2606 OID 888335)
-- Name: document_notes document_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.document_notes
    ADD CONSTRAINT document_notes_pkey PRIMARY KEY (id);


--
-- TOC entry 5496 (class 2606 OID 887963)
-- Name: documents documents_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.documents
    ADD CONSTRAINT documents_pkey PRIMARY KEY (id);


--
-- TOC entry 5605 (class 2606 OID 888391)
-- Name: email_label_email_log email_label_mail_report_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_label_email_log
    ADD CONSTRAINT email_label_mail_report_pkey PRIMARY KEY (id);


--
-- TOC entry 5599 (class 2606 OID 888365)
-- Name: email_labels email_labels_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_labels
    ADD CONSTRAINT email_labels_pkey PRIMARY KEY (id);


--
-- TOC entry 5607 (class 2606 OID 889398)
-- Name: email_label_email_log email_log_label_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_label_email_log
    ADD CONSTRAINT email_log_label_unique UNIQUE (email_log_id, email_label_id);


--
-- TOC entry 5687 (class 2606 OID 889472)
-- Name: email_templates email_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_pkey PRIMARY KEY (id);


--
-- TOC entry 5556 (class 2606 OID 888161)
-- Name: email_verifications email_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_verifications
    ADD CONSTRAINT email_verifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5499 (class 2606 OID 887971)
-- Name: emails emails_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.emails
    ADD CONSTRAINT emails_pkey PRIMARY KEY (id);


--
-- TOC entry 5415 (class 2606 OID 887676)
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 5417 (class 2606 OID 887678)
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- TOC entry 5718 (class 2606 OID 889667)
-- Name: front_desk_check_ins front_desk_check_ins_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.front_desk_check_ins
    ADD CONSTRAINT front_desk_check_ins_pkey PRIMARY KEY (id);


--
-- TOC entry 5413 (class 2606 OID 887659)
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- TOC entry 5410 (class 2606 OID 887644)
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- TOC entry 5672 (class 2606 OID 889431)
-- Name: lead_matter_references lead_matter_ref_type_lead_matter_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_ref_type_lead_matter_unique UNIQUE (type, lead_id, matter_id);


--
-- TOC entry 5676 (class 2606 OID 889409)
-- Name: lead_matter_references lead_matter_references_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_pkey PRIMARY KEY (id);


--
-- TOC entry 5680 (class 2606 OID 889446)
-- Name: lead_reminders lead_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_pkey PRIMARY KEY (id);


--
-- TOC entry 5612 (class 2606 OID 888411)
-- Name: email_log_attachments mail_report_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_log_attachments
    ADD CONSTRAINT mail_report_attachments_pkey PRIMARY KEY (id);


--
-- TOC entry 5503 (class 2606 OID 887979)
-- Name: email_logs mail_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_logs
    ADD CONSTRAINT mail_reports_pkey PRIMARY KEY (id);


--
-- TOC entry 5668 (class 2606 OID 889383)
-- Name: matter_reminders matter_reminders_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_pkey PRIMARY KEY (id);


--
-- TOC entry 5513 (class 2606 OID 888003)
-- Name: matters matters_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matters
    ADD CONSTRAINT matters_pkey PRIMARY KEY (id);


--
-- TOC entry 5655 (class 2606 OID 889024)
-- Name: message_attachments message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_pkey PRIMARY KEY (id);


--
-- TOC entry 5562 (class 2606 OID 888184)
-- Name: message_recipients message_recipients_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_recipients
    ADD CONSTRAINT message_recipients_pkey PRIMARY KEY (id);


--
-- TOC entry 5492 (class 2606 OID 887947)
-- Name: messages messages_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.messages
    ADD CONSTRAINT messages_pkey PRIMARY KEY (id);


--
-- TOC entry 5392 (class 2606 OID 887572)
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- TOC entry 5708 (class 2606 OID 889628)
-- Name: nomination_document_types nomination_document_types_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nomination_document_types
    ADD CONSTRAINT nomination_document_types_pkey PRIMARY KEY (id);


--
-- TOC entry 5480 (class 2606 OID 887907)
-- Name: notes notes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notes
    ADD CONSTRAINT notes_pkey PRIMARY KEY (id);


--
-- TOC entry 5474 (class 2606 OID 887897)
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5400 (class 2606 OID 887599)
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- TOC entry 5545 (class 2606 OID 888112)
-- Name: phone_verifications phone_verifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.phone_verifications
    ADD CONSTRAINT phone_verifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5530 (class 2606 OID 888073)
-- Name: refresh_tokens refresh_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_pkey PRIMARY KEY (id);


--
-- TOC entry 5533 (class 2606 OID 888083)
-- Name: refresh_tokens refresh_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_token_unique UNIQUE (token);


--
-- TOC entry 5403 (class 2606 OID 887609)
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- TOC entry 5465 (class 2606 OID 887879)
-- Name: signers signers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.signers
    ADD CONSTRAINT signers_pkey PRIMARY KEY (id);


--
-- TOC entry 5579 (class 2606 OID 888277)
-- Name: sms_logs sms_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms_logs
    ADD CONSTRAINT sms_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 5585 (class 2606 OID 888306)
-- Name: sms_templates sms_templates_alias_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms_templates
    ADD CONSTRAINT sms_templates_alias_unique UNIQUE (alias);


--
-- TOC entry 5590 (class 2606 OID 888301)
-- Name: sms_templates sms_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sms_templates
    ADD CONSTRAINT sms_templates_pkey PRIMARY KEY (id);


--
-- TOC entry 5647 (class 2606 OID 888663)
-- Name: staff staff_email_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_email_unique UNIQUE (email);


--
-- TOC entry 5649 (class 2606 OID 888651)
-- Name: staff staff_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_pkey PRIMARY KEY (id);


--
-- TOC entry 5509 (class 2606 OID 887995)
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- TOC entry 5482 (class 2606 OID 887915)
-- Name: teams teams_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.teams
    ADD CONSTRAINT teams_pkey PRIMARY KEY (id);


--
-- TOC entry 5620 (class 2606 OID 888496)
-- Name: staff_login_logs user_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff_login_logs
    ADD CONSTRAINT user_logs_pkey PRIMARY KEY (id);


--
-- TOC entry 5486 (class 2606 OID 887931)
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (id);


--
-- TOC entry 5519 (class 2606 OID 888027)
-- Name: workflow_stages workflow_stages_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflow_stages
    ADD CONSTRAINT workflow_stages_pkey PRIMARY KEY (id);


--
-- TOC entry 5657 (class 2606 OID 889041)
-- Name: workflows workflows_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.workflows
    ADD CONSTRAINT workflows_pkey PRIMARY KEY (id);


--
-- TOC entry 5505 (class 1259 OID 888416)
-- Name: account_client_receipts_pdf_document_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX account_client_receipts_pdf_document_id_index ON public.account_client_receipts USING btree (pdf_document_id);


--
-- TOC entry 5457 (class 1259 OID 888627)
-- Name: activities_logs_activity_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX activities_logs_activity_type_index ON public.activities_logs USING btree (activity_type);


--
-- TOC entry 5458 (class 1259 OID 888628)
-- Name: activities_logs_client_id_activity_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX activities_logs_client_id_activity_type_index ON public.activities_logs USING btree (client_id, activity_type);


--
-- TOC entry 5459 (class 1259 OID 887870)
-- Name: activities_logs_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX activities_logs_client_id_index ON public.activities_logs USING btree (client_id);


--
-- TOC entry 5460 (class 1259 OID 888625)
-- Name: activities_logs_client_id_source_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX activities_logs_client_id_source_index ON public.activities_logs USING btree (client_id, source);


--
-- TOC entry 5463 (class 1259 OID 888309)
-- Name: activities_logs_sms_log_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX activities_logs_sms_log_id_index ON public.activities_logs USING btree (sms_log_id);


--
-- TOC entry 5393 (class 1259 OID 888502)
-- Name: admins_archived_by_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX admins_archived_by_index ON public.admins USING btree (archived_by);


--
-- TOC entry 5567 (class 1259 OID 888218)
-- Name: anzsco_occupations_is_active_anzsco_code_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX anzsco_occupations_is_active_anzsco_code_index ON public.anzsco_occupations USING btree (is_active, anzsco_code);


--
-- TOC entry 5568 (class 1259 OID 888224)
-- Name: anzsco_occupations_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX anzsco_occupations_is_active_index ON public.anzsco_occupations USING btree (is_active);


--
-- TOC entry 5569 (class 1259 OID 888219)
-- Name: anzsco_occupations_is_active_occupation_title_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX anzsco_occupations_is_active_occupation_title_index ON public.anzsco_occupations USING btree (is_active, occupation_title);


--
-- TOC entry 5570 (class 1259 OID 888222)
-- Name: anzsco_occupations_occupation_title_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX anzsco_occupations_occupation_title_index ON public.anzsco_occupations USING btree (occupation_title);


--
-- TOC entry 5571 (class 1259 OID 888223)
-- Name: anzsco_occupations_occupation_title_normalized_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX anzsco_occupations_occupation_title_normalized_index ON public.anzsco_occupations USING btree (occupation_title_normalized);


--
-- TOC entry 5629 (class 1259 OID 888557)
-- Name: appointment_payments_appointment_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_appointment_id_index ON public.appointment_payments USING btree (appointment_id);


--
-- TOC entry 5630 (class 1259 OID 888559)
-- Name: appointment_payments_charge_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_charge_id_index ON public.appointment_payments USING btree (charge_id);


--
-- TOC entry 5631 (class 1259 OID 888563)
-- Name: appointment_payments_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_created_at_index ON public.appointment_payments USING btree (created_at);


--
-- TOC entry 5632 (class 1259 OID 888560)
-- Name: appointment_payments_customer_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_customer_id_index ON public.appointment_payments USING btree (customer_id);


--
-- TOC entry 5633 (class 1259 OID 888562)
-- Name: appointment_payments_payment_gateway_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_payment_gateway_index ON public.appointment_payments USING btree (payment_gateway);


--
-- TOC entry 5636 (class 1259 OID 888561)
-- Name: appointment_payments_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_status_index ON public.appointment_payments USING btree (status);


--
-- TOC entry 5637 (class 1259 OID 888558)
-- Name: appointment_payments_transaction_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_payments_transaction_id_index ON public.appointment_payments USING btree (transaction_id);


--
-- TOC entry 5440 (class 1259 OID 887809)
-- Name: appointment_sync_logs_started_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_sync_logs_started_at_index ON public.appointment_sync_logs USING btree (started_at);


--
-- TOC entry 5441 (class 1259 OID 887808)
-- Name: appointment_sync_logs_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_sync_logs_status_index ON public.appointment_sync_logs USING btree (status);


--
-- TOC entry 5442 (class 1259 OID 887807)
-- Name: appointment_sync_logs_sync_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX appointment_sync_logs_sync_type_index ON public.appointment_sync_logs USING btree (sync_type);


--
-- TOC entry 5426 (class 1259 OID 887772)
-- Name: booking_appointments_appointment_datetime_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_appointment_datetime_index ON public.booking_appointments USING btree (appointment_datetime);


--
-- TOC entry 5429 (class 1259 OID 887770)
-- Name: booking_appointments_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_client_id_index ON public.booking_appointments USING btree (client_id);


--
-- TOC entry 5430 (class 1259 OID 887771)
-- Name: booking_appointments_consultant_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_consultant_id_index ON public.booking_appointments USING btree (consultant_id);


--
-- TOC entry 5431 (class 1259 OID 887777)
-- Name: booking_appointments_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_created_at_index ON public.booking_appointments USING btree (created_at);


--
-- TOC entry 5432 (class 1259 OID 887774)
-- Name: booking_appointments_location_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_location_index ON public.booking_appointments USING btree (location);


--
-- TOC entry 5435 (class 1259 OID 887775)
-- Name: booking_appointments_service_id_noe_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_service_id_noe_id_index ON public.booking_appointments USING btree (service_id, noe_id);


--
-- TOC entry 5436 (class 1259 OID 887773)
-- Name: booking_appointments_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_status_index ON public.booking_appointments USING btree (status);


--
-- TOC entry 5437 (class 1259 OID 887776)
-- Name: booking_appointments_sync_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX booking_appointments_sync_status_index ON public.booking_appointments USING btree (sync_status);


--
-- TOC entry 5487 (class 1259 OID 888172)
-- Name: client_addresses_country_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_addresses_country_index ON public.client_addresses USING btree (country);


--
-- TOC entry 5490 (class 1259 OID 888171)
-- Name: client_addresses_suburb_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_addresses_suburb_index ON public.client_addresses USING btree (suburb);


--
-- TOC entry 5638 (class 1259 OID 888623)
-- Name: client_art_references_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_art_references_client_id_index ON public.client_art_references USING btree (client_id);


--
-- TOC entry 5639 (class 1259 OID 888624)
-- Name: client_art_references_client_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_art_references_client_matter_id_index ON public.client_art_references USING btree (client_matter_id);


--
-- TOC entry 5640 (class 1259 OID 888981)
-- Name: client_art_references_is_pinned_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_art_references_is_pinned_index ON public.client_art_references USING btree (is_pinned);


--
-- TOC entry 5535 (class 1259 OID 888092)
-- Name: client_contacts_admin_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_contacts_admin_id_index ON public.client_contacts USING btree (admin_id);


--
-- TOC entry 5536 (class 1259 OID 888093)
-- Name: client_contacts_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_contacts_client_id_index ON public.client_contacts USING btree (client_id);


--
-- TOC entry 5537 (class 1259 OID 888124)
-- Name: client_contacts_is_verified_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_contacts_is_verified_index ON public.client_contacts USING btree (is_verified);


--
-- TOC entry 5546 (class 1259 OID 888133)
-- Name: client_emails_admin_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_emails_admin_id_index ON public.client_emails USING btree (admin_id);


--
-- TOC entry 5547 (class 1259 OID 888134)
-- Name: client_emails_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_emails_client_id_index ON public.client_emails USING btree (client_id);


--
-- TOC entry 5548 (class 1259 OID 888144)
-- Name: client_emails_is_verified_verification_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_emails_is_verified_verification_token_index ON public.client_emails USING btree (is_verified, verification_token);


--
-- TOC entry 5551 (class 1259 OID 888145)
-- Name: client_emails_token_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_emails_token_expires_at_index ON public.client_emails USING btree (token_expires_at);


--
-- TOC entry 5451 (class 1259 OID 887838)
-- Name: client_experiences_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_experiences_client_id_index ON public.client_experiences USING btree (client_id);


--
-- TOC entry 5650 (class 1259 OID 889005)
-- Name: client_matter_payment_forms_verifications_client_matter_id_inde; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matter_payment_forms_verifications_client_matter_id_inde ON public.client_matter_payment_forms_verifications USING btree (client_matter_id);


--
-- TOC entry 5660 (class 1259 OID 889369)
-- Name: client_matter_references_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matter_references_client_id_index ON public.client_matter_references USING btree (client_id);


--
-- TOC entry 5661 (class 1259 OID 889370)
-- Name: client_matter_references_client_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matter_references_client_matter_id_index ON public.client_matter_references USING btree (client_matter_id);


--
-- TOC entry 5662 (class 1259 OID 889371)
-- Name: client_matter_references_is_pinned_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matter_references_is_pinned_index ON public.client_matter_references USING btree (is_pinned);


--
-- TOC entry 5665 (class 1259 OID 889368)
-- Name: client_matter_references_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matter_references_type_index ON public.client_matter_references USING btree (type);


--
-- TOC entry 5466 (class 1259 OID 887888)
-- Name: client_matters_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_matters_client_id_index ON public.client_matters USING btree (client_id);


--
-- TOC entry 5418 (class 1259 OID 887688)
-- Name: client_passport_informations_admin_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_passport_informations_admin_id_index ON public.client_passport_informations USING btree (admin_id);


--
-- TOC entry 5419 (class 1259 OID 887687)
-- Name: client_passport_informations_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_passport_informations_client_id_index ON public.client_passport_informations USING btree (client_id);


--
-- TOC entry 5443 (class 1259 OID 887820)
-- Name: client_qualifications_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_qualifications_client_id_index ON public.client_qualifications USING btree (client_id);


--
-- TOC entry 5447 (class 1259 OID 887829)
-- Name: client_spouse_details_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX client_spouse_details_client_id_index ON public.client_spouse_details USING btree (client_id);


--
-- TOC entry 5621 (class 1259 OID 888519)
-- Name: companies_admin_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX companies_admin_id_index ON public.companies USING btree (admin_id);


--
-- TOC entry 5624 (class 1259 OID 888521)
-- Name: companies_company_name_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX companies_company_name_index ON public.companies USING btree (company_name);


--
-- TOC entry 5625 (class 1259 OID 888520)
-- Name: companies_contact_person_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX companies_contact_person_id_index ON public.companies USING btree (contact_person_id);


--
-- TOC entry 5693 (class 1259 OID 889524)
-- Name: company_directors_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_directors_company_id_index ON public.company_directors USING btree (company_id);


--
-- TOC entry 5696 (class 1259 OID 889548)
-- Name: company_nominations_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_nominations_company_id_index ON public.company_nominations USING btree (company_id);


--
-- TOC entry 5709 (class 1259 OID 889653)
-- Name: company_sponsorships_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_sponsorships_company_id_index ON public.company_sponsorships USING btree (company_id);


--
-- TOC entry 5690 (class 1259 OID 889502)
-- Name: company_trading_names_company_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX company_trading_names_company_id_index ON public.company_trading_names USING btree (company_id);


--
-- TOC entry 5522 (class 1259 OID 888056)
-- Name: device_tokens_device_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX device_tokens_device_token_index ON public.device_tokens USING btree (device_token);


--
-- TOC entry 5527 (class 1259 OID 888055)
-- Name: device_tokens_user_id_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX device_tokens_user_id_is_active_index ON public.device_tokens USING btree (user_id, is_active);


--
-- TOC entry 5591 (class 1259 OID 888338)
-- Name: document_notes_action_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_notes_action_type_index ON public.document_notes USING btree (action_type);


--
-- TOC entry 5592 (class 1259 OID 888339)
-- Name: document_notes_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_notes_created_at_index ON public.document_notes USING btree (created_at);


--
-- TOC entry 5593 (class 1259 OID 888337)
-- Name: document_notes_created_by_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_notes_created_by_index ON public.document_notes USING btree (created_by);


--
-- TOC entry 5594 (class 1259 OID 888336)
-- Name: document_notes_document_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX document_notes_document_id_index ON public.document_notes USING btree (document_id);


--
-- TOC entry 5602 (class 1259 OID 888395)
-- Name: email_label_mail_report_email_label_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_label_mail_report_email_label_id_index ON public.email_label_email_log USING btree (email_label_id);


--
-- TOC entry 5603 (class 1259 OID 888394)
-- Name: email_label_mail_report_mail_report_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_label_mail_report_mail_report_id_index ON public.email_label_email_log USING btree (email_log_id);


--
-- TOC entry 5597 (class 1259 OID 888368)
-- Name: email_labels_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_labels_is_active_index ON public.email_labels USING btree (is_active);


--
-- TOC entry 5600 (class 1259 OID 888367)
-- Name: email_labels_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_labels_type_index ON public.email_labels USING btree (type);


--
-- TOC entry 5601 (class 1259 OID 888366)
-- Name: email_labels_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_labels_user_id_index ON public.email_labels USING btree (user_id);


--
-- TOC entry 5683 (class 1259 OID 889482)
-- Name: email_templates_alias_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_templates_alias_index ON public.email_templates USING btree (alias);


--
-- TOC entry 5684 (class 1259 OID 889481)
-- Name: email_templates_matter_first_unique; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX email_templates_matter_first_unique ON public.email_templates USING btree (matter_id) WHERE ((type)::text = 'matter_first'::text);


--
-- TOC entry 5685 (class 1259 OID 889480)
-- Name: email_templates_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_templates_matter_id_index ON public.email_templates USING btree (matter_id);


--
-- TOC entry 5688 (class 1259 OID 889479)
-- Name: email_templates_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_templates_type_index ON public.email_templates USING btree (type);


--
-- TOC entry 5689 (class 1259 OID 889478)
-- Name: email_templates_type_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_templates_type_matter_id_index ON public.email_templates USING btree (type, matter_id);


--
-- TOC entry 5552 (class 1259 OID 888162)
-- Name: email_verifications_client_email_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verifications_client_email_id_index ON public.email_verifications USING btree (client_email_id);


--
-- TOC entry 5553 (class 1259 OID 888165)
-- Name: email_verifications_email_token_sent_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verifications_email_token_sent_at_index ON public.email_verifications USING btree (email, token_sent_at);


--
-- TOC entry 5554 (class 1259 OID 888166)
-- Name: email_verifications_is_verified_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verifications_is_verified_index ON public.email_verifications USING btree (is_verified);


--
-- TOC entry 5557 (class 1259 OID 888164)
-- Name: email_verifications_token_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verifications_token_expires_at_index ON public.email_verifications USING btree (token_expires_at);


--
-- TOC entry 5558 (class 1259 OID 888163)
-- Name: email_verifications_verification_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX email_verifications_verification_token_index ON public.email_verifications USING btree (verification_token);


--
-- TOC entry 5712 (class 1259 OID 889671)
-- Name: front_desk_check_ins_admin_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX front_desk_check_ins_admin_id_index ON public.front_desk_check_ins USING btree (admin_id);


--
-- TOC entry 5713 (class 1259 OID 889672)
-- Name: front_desk_check_ins_appointment_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX front_desk_check_ins_appointment_id_index ON public.front_desk_check_ins USING btree (appointment_id);


--
-- TOC entry 5714 (class 1259 OID 889669)
-- Name: front_desk_check_ins_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX front_desk_check_ins_client_id_index ON public.front_desk_check_ins USING btree (client_id);


--
-- TOC entry 5715 (class 1259 OID 889668)
-- Name: front_desk_check_ins_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX front_desk_check_ins_created_at_index ON public.front_desk_check_ins USING btree (created_at);


--
-- TOC entry 5716 (class 1259 OID 889670)
-- Name: front_desk_check_ins_lead_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX front_desk_check_ins_lead_id_index ON public.front_desk_check_ins USING btree (lead_id);


--
-- TOC entry 5398 (class 1259 OID 888504)
-- Name: idx_admins_is_company; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admins_is_company ON public.admins USING btree (is_company) WHERE (is_company = true);


--
-- TOC entry 5643 (class 1259 OID 888620)
-- Name: idx_art_client_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_art_client_status ON public.client_art_references USING btree (client_id, status_of_file);


--
-- TOC entry 5644 (class 1259 OID 888622)
-- Name: idx_art_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_art_status ON public.client_art_references USING btree (status_of_file);


--
-- TOC entry 5645 (class 1259 OID 888621)
-- Name: idx_art_submission_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_art_submission_date ON public.client_art_references USING btree (submission_last_date);


--
-- TOC entry 5615 (class 1259 OID 888441)
-- Name: idx_audit_client_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_audit_client_id ON public.clientportal_details_audit USING btree (client_id);


--
-- TOC entry 5616 (class 1259 OID 888444)
-- Name: idx_audit_client_meta; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_audit_client_meta ON public.clientportal_details_audit USING btree (client_id, meta_key);


--
-- TOC entry 5617 (class 1259 OID 888442)
-- Name: idx_audit_meta_key; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_audit_meta_key ON public.clientportal_details_audit USING btree (meta_key);


--
-- TOC entry 5618 (class 1259 OID 888443)
-- Name: idx_audit_updated_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_audit_updated_at ON public.clientportal_details_audit USING btree (updated_at);


--
-- TOC entry 5701 (class 1259 OID 889615)
-- Name: idx_cag_approver_queue; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cag_approver_queue ON public.client_access_grants USING btree (status, approved_by_staff_id);


--
-- TOC entry 5702 (class 1259 OID 889617)
-- Name: idx_cag_ends_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cag_ends_at ON public.client_access_grants USING btree (ends_at) WHERE ((status)::text = 'active'::text);


--
-- TOC entry 5703 (class 1259 OID 889613)
-- Name: idx_cag_staff_admin_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cag_staff_admin_status ON public.client_access_grants USING btree (staff_id, admin_id, status);


--
-- TOC entry 5704 (class 1259 OID 889614)
-- Name: idx_cag_status_requested; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cag_status_requested ON public.client_access_grants USING btree (status, requested_at);


--
-- TOC entry 5424 (class 1259 OID 887707)
-- Name: idx_calendar_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_calendar_type ON public.appointment_consultants USING btree (calendar_type);


--
-- TOC entry 5446 (class 1259 OID 888236)
-- Name: idx_client_country; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_client_country ON public.client_qualifications USING btree (client_id, country);


--
-- TOC entry 5454 (class 1259 OID 888244)
-- Name: idx_client_experiences_client_job_country; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_client_experiences_client_job_country ON public.client_experiences USING btree (client_id, job_country);


--
-- TOC entry 5628 (class 1259 OID 888534)
-- Name: idx_companies_contact_person_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_companies_contact_person_id ON public.companies USING btree (contact_person_id) WHERE (contact_person_id IS NOT NULL);


--
-- TOC entry 5497 (class 1259 OID 888464)
-- Name: idx_documents_office; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_documents_office ON public.documents USING btree (office_id);


--
-- TOC entry 5425 (class 1259 OID 887708)
-- Name: idx_is_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_is_active ON public.appointment_consultants USING btree (is_active);


--
-- TOC entry 5511 (class 1259 OID 888506)
-- Name: idx_matters_is_for_company; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_matters_is_for_company ON public.matters USING btree (is_for_company) WHERE (is_for_company = true);


--
-- TOC entry 5469 (class 1259 OID 888462)
-- Name: idx_matters_office; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_matters_office ON public.client_matters USING btree (office_id);


--
-- TOC entry 5470 (class 1259 OID 888463)
-- Name: idx_matters_office_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_matters_office_status ON public.client_matters USING btree (office_id, matter_status);


--
-- TOC entry 5475 (class 1259 OID 889327)
-- Name: idx_notes_action_assigned; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notes_action_assigned ON public.notes USING btree (type, status, assigned_to, is_action);


--
-- TOC entry 5476 (class 1259 OID 889329)
-- Name: idx_notes_client_tasks; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notes_client_tasks ON public.notes USING btree (type, client_id, is_action);


--
-- TOC entry 5477 (class 1259 OID 889330)
-- Name: idx_notes_completed_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notes_completed_date ON public.notes USING btree (type, status, action_date);


--
-- TOC entry 5478 (class 1259 OID 889328)
-- Name: idx_notes_task_group_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notes_task_group_date ON public.notes USING btree (type, task_group, action_date);


--
-- TOC entry 5471 (class 1259 OID 888460)
-- Name: idx_notifications_receiver_type_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_receiver_type_status ON public.notifications USING btree (receiver_id, notification_type, receiver_status, created_at);


--
-- TOC entry 5472 (class 1259 OID 888461)
-- Name: idx_notifications_type_receiver_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_notifications_type_receiver_status ON public.notifications USING btree (notification_type, receiver_id, receiver_status, created_at);


--
-- TOC entry 5450 (class 1259 OID 888241)
-- Name: idx_spouse_client; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_spouse_client ON public.client_spouse_details USING btree (client_id);


--
-- TOC entry 5411 (class 1259 OID 887645)
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- TOC entry 5673 (class 1259 OID 889433)
-- Name: lead_matter_references_lead_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_matter_references_lead_id_index ON public.lead_matter_references USING btree (lead_id);


--
-- TOC entry 5674 (class 1259 OID 889434)
-- Name: lead_matter_references_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_matter_references_matter_id_index ON public.lead_matter_references USING btree (matter_id);


--
-- TOC entry 5677 (class 1259 OID 889432)
-- Name: lead_matter_references_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_matter_references_type_index ON public.lead_matter_references USING btree (type);


--
-- TOC entry 5678 (class 1259 OID 889459)
-- Name: lead_reminders_lead_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_reminders_lead_id_index ON public.lead_reminders USING btree (lead_id);


--
-- TOC entry 5681 (class 1259 OID 889457)
-- Name: lead_reminders_visa_lead_type_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_reminders_visa_lead_type_idx ON public.lead_reminders USING btree (visa_type, lead_id, type);


--
-- TOC entry 5682 (class 1259 OID 889458)
-- Name: lead_reminders_visa_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX lead_reminders_visa_type_index ON public.lead_reminders USING btree (visa_type);


--
-- TOC entry 5608 (class 1259 OID 888414)
-- Name: mail_report_attachments_created_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_report_attachments_created_at_index ON public.email_log_attachments USING btree (created_at);


--
-- TOC entry 5609 (class 1259 OID 888413)
-- Name: mail_report_attachments_is_inline_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_report_attachments_is_inline_index ON public.email_log_attachments USING btree (is_inline);


--
-- TOC entry 5610 (class 1259 OID 888412)
-- Name: mail_report_attachments_mail_report_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_report_attachments_mail_report_id_index ON public.email_log_attachments USING btree (email_log_id);


--
-- TOC entry 5500 (class 1259 OID 888381)
-- Name: mail_reports_file_hash_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_reports_file_hash_index ON public.email_logs USING btree (file_hash);


--
-- TOC entry 5501 (class 1259 OID 888379)
-- Name: mail_reports_message_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_reports_message_id_index ON public.email_logs USING btree (message_id);


--
-- TOC entry 5504 (class 1259 OID 888380)
-- Name: mail_reports_thread_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX mail_reports_thread_id_index ON public.email_logs USING btree (thread_id);


--
-- TOC entry 5666 (class 1259 OID 889396)
-- Name: matter_reminders_client_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX matter_reminders_client_matter_id_index ON public.matter_reminders USING btree (client_matter_id);


--
-- TOC entry 5669 (class 1259 OID 889394)
-- Name: matter_reminders_visa_matter_type_idx; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX matter_reminders_visa_matter_type_idx ON public.matter_reminders USING btree (visa_type, client_matter_id, type);


--
-- TOC entry 5670 (class 1259 OID 889395)
-- Name: matter_reminders_visa_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX matter_reminders_visa_type_index ON public.matter_reminders USING btree (visa_type);


--
-- TOC entry 5653 (class 1259 OID 889030)
-- Name: message_attachments_message_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX message_attachments_message_id_index ON public.message_attachments USING btree (message_id);


--
-- TOC entry 5559 (class 1259 OID 888190)
-- Name: message_recipients_message_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX message_recipients_message_id_index ON public.message_recipients USING btree (message_id);


--
-- TOC entry 5560 (class 1259 OID 888193)
-- Name: message_recipients_message_id_recipient_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX message_recipients_message_id_recipient_id_index ON public.message_recipients USING btree (message_id, recipient_id);


--
-- TOC entry 5563 (class 1259 OID 888191)
-- Name: message_recipients_recipient_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX message_recipients_recipient_id_index ON public.message_recipients USING btree (recipient_id);


--
-- TOC entry 5564 (class 1259 OID 888192)
-- Name: message_recipients_recipient_id_is_read_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX message_recipients_recipient_id_is_read_index ON public.message_recipients USING btree (recipient_id, is_read);


--
-- TOC entry 5705 (class 1259 OID 889630)
-- Name: nomination_document_types_client_id_client_matter_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX nomination_document_types_client_id_client_matter_id_index ON public.nomination_document_types USING btree (client_id, client_matter_id);


--
-- TOC entry 5706 (class 1259 OID 889629)
-- Name: nomination_document_types_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX nomination_document_types_client_id_index ON public.nomination_document_types USING btree (client_id);


--
-- TOC entry 5540 (class 1259 OID 888113)
-- Name: phone_verifications_client_contact_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX phone_verifications_client_contact_id_index ON public.phone_verifications USING btree (client_contact_id);


--
-- TOC entry 5541 (class 1259 OID 888114)
-- Name: phone_verifications_otp_code_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX phone_verifications_otp_code_index ON public.phone_verifications USING btree (otp_code);


--
-- TOC entry 5542 (class 1259 OID 888116)
-- Name: phone_verifications_otp_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX phone_verifications_otp_expires_at_index ON public.phone_verifications USING btree (otp_expires_at);


--
-- TOC entry 5543 (class 1259 OID 888115)
-- Name: phone_verifications_phone_country_code_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX phone_verifications_phone_country_code_index ON public.phone_verifications USING btree (phone, country_code);


--
-- TOC entry 5528 (class 1259 OID 888081)
-- Name: refresh_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX refresh_tokens_expires_at_index ON public.refresh_tokens USING btree (expires_at);


--
-- TOC entry 5531 (class 1259 OID 888080)
-- Name: refresh_tokens_token_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX refresh_tokens_token_index ON public.refresh_tokens USING btree (token);


--
-- TOC entry 5534 (class 1259 OID 888079)
-- Name: refresh_tokens_user_id_is_revoked_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX refresh_tokens_user_id_is_revoked_index ON public.refresh_tokens USING btree (user_id, is_revoked);


--
-- TOC entry 5401 (class 1259 OID 887611)
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- TOC entry 5404 (class 1259 OID 887610)
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- TOC entry 5574 (class 1259 OID 888280)
-- Name: sms_logs_client_contact_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_client_contact_id_index ON public.sms_logs USING btree (client_contact_id);


--
-- TOC entry 5575 (class 1259 OID 888278)
-- Name: sms_logs_client_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_client_id_index ON public.sms_logs USING btree (client_id);


--
-- TOC entry 5576 (class 1259 OID 888285)
-- Name: sms_logs_client_id_sent_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_client_id_sent_at_index ON public.sms_logs USING btree (client_id, sent_at);


--
-- TOC entry 5577 (class 1259 OID 888283)
-- Name: sms_logs_message_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_message_type_index ON public.sms_logs USING btree (message_type);


--
-- TOC entry 5580 (class 1259 OID 888282)
-- Name: sms_logs_provider_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_provider_index ON public.sms_logs USING btree (provider);


--
-- TOC entry 5581 (class 1259 OID 888279)
-- Name: sms_logs_sender_id_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_sender_id_index ON public.sms_logs USING btree (sender_id);


--
-- TOC entry 5582 (class 1259 OID 888284)
-- Name: sms_logs_sent_at_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_sent_at_index ON public.sms_logs USING btree (sent_at);


--
-- TOC entry 5583 (class 1259 OID 888281)
-- Name: sms_logs_status_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_logs_status_index ON public.sms_logs USING btree (status);


--
-- TOC entry 5586 (class 1259 OID 888302)
-- Name: sms_templates_category_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_templates_category_index ON public.sms_templates USING btree (category);


--
-- TOC entry 5587 (class 1259 OID 888304)
-- Name: sms_templates_is_active_category_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_templates_is_active_category_index ON public.sms_templates USING btree (is_active, category);


--
-- TOC entry 5588 (class 1259 OID 888303)
-- Name: sms_templates_is_active_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX sms_templates_is_active_index ON public.sms_templates USING btree (is_active);


--
-- TOC entry 5510 (class 1259 OID 888477)
-- Name: tags_tag_type_index; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX tags_tag_type_index ON public.tags USING btree (tag_type);


--
-- TOC entry 5719 (class 2606 OID 888497)
-- Name: admins admins_archived_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_archived_by_foreign FOREIGN KEY (archived_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5720 (class 2606 OID 887765)
-- Name: booking_appointments booking_appointments_assigned_by_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_assigned_by_admin_id_foreign FOREIGN KEY (assigned_by_admin_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5721 (class 2606 OID 888422)
-- Name: booking_appointments booking_appointments_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5722 (class 2606 OID 887760)
-- Name: booking_appointments booking_appointments_consultant_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.booking_appointments
    ADD CONSTRAINT booking_appointments_consultant_id_foreign FOREIGN KEY (consultant_id) REFERENCES public.appointment_consultants(id) ON DELETE SET NULL;


--
-- TOC entry 5760 (class 2606 OID 889593)
-- Name: client_access_grants client_access_grants_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5761 (class 2606 OID 889608)
-- Name: client_access_grants client_access_grants_approved_by_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_approved_by_staff_id_foreign FOREIGN KEY (approved_by_staff_id) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5762 (class 2606 OID 889598)
-- Name: client_access_grants client_access_grants_office_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_office_id_foreign FOREIGN KEY (office_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- TOC entry 5763 (class 2606 OID 889588)
-- Name: client_access_grants client_access_grants_staff_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_staff_id_foreign FOREIGN KEY (staff_id) REFERENCES public.staff(id) ON DELETE CASCADE;


--
-- TOC entry 5764 (class 2606 OID 889603)
-- Name: client_access_grants client_access_grants_team_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_access_grants
    ADD CONSTRAINT client_access_grants_team_id_foreign FOREIGN KEY (team_id) REFERENCES public.teams(id) ON DELETE SET NULL;


--
-- TOC entry 5734 (class 2606 OID 888595)
-- Name: client_art_references client_art_references_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5735 (class 2606 OID 888600)
-- Name: client_art_references client_art_references_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- TOC entry 5736 (class 2606 OID 888610)
-- Name: client_art_references client_art_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5737 (class 2606 OID 888615)
-- Name: client_art_references client_art_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_art_references
    ADD CONSTRAINT client_art_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5727 (class 2606 OID 888468)
-- Name: client_contacts client_contacts_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_contacts
    ADD CONSTRAINT client_contacts_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5728 (class 2606 OID 888139)
-- Name: client_emails client_emails_verified_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_emails
    ADD CONSTRAINT client_emails_verified_by_foreign FOREIGN KEY (verified_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5740 (class 2606 OID 889000)
-- Name: client_matter_payment_forms_verifications client_matter_payment_forms_verifications_client_matter_id_fore; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_payment_forms_verifications
    ADD CONSTRAINT client_matter_payment_forms_verifications_client_matter_id_fore FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- TOC entry 5742 (class 2606 OID 889346)
-- Name: client_matter_references client_matter_references_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5743 (class 2606 OID 889351)
-- Name: client_matter_references client_matter_references_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- TOC entry 5744 (class 2606 OID 889356)
-- Name: client_matter_references client_matter_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5745 (class 2606 OID 889361)
-- Name: client_matter_references client_matter_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_matter_references
    ADD CONSTRAINT client_matter_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5724 (class 2606 OID 888417)
-- Name: client_occupations client_occupations_anzsco_occupation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_occupations
    ADD CONSTRAINT client_occupations_anzsco_occupation_id_foreign FOREIGN KEY (anzsco_occupation_id) REFERENCES public.anzsco_occupations(id) ON DELETE SET NULL;


--
-- TOC entry 5723 (class 2606 OID 888251)
-- Name: client_spouse_details client_spouse_details_related_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.client_spouse_details
    ADD CONSTRAINT client_spouse_details_related_client_id_foreign FOREIGN KEY (related_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5730 (class 2606 OID 888445)
-- Name: clientportal_details_audit clientportal_details_audit_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientportal_details_audit
    ADD CONSTRAINT clientportal_details_audit_client_id_foreign FOREIGN KEY (client_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5731 (class 2606 OID 888450)
-- Name: clientportal_details_audit clientportal_details_audit_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.clientportal_details_audit
    ADD CONSTRAINT clientportal_details_audit_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5732 (class 2606 OID 888524)
-- Name: companies companies_admin_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_admin_id_foreign FOREIGN KEY (admin_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5733 (class 2606 OID 888529)
-- Name: companies companies_contact_person_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.companies
    ADD CONSTRAINT companies_contact_person_id_foreign FOREIGN KEY (contact_person_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5756 (class 2606 OID 889519)
-- Name: company_directors company_directors_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- TOC entry 5757 (class 2606 OID 889551)
-- Name: company_directors company_directors_director_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_directors
    ADD CONSTRAINT company_directors_director_client_id_foreign FOREIGN KEY (director_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5758 (class 2606 OID 889538)
-- Name: company_nominations company_nominations_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- TOC entry 5759 (class 2606 OID 889543)
-- Name: company_nominations company_nominations_nominated_client_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_nominations
    ADD CONSTRAINT company_nominations_nominated_client_id_foreign FOREIGN KEY (nominated_client_id) REFERENCES public.admins(id) ON DELETE SET NULL;


--
-- TOC entry 5765 (class 2606 OID 889648)
-- Name: company_sponsorships company_sponsorships_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_sponsorships
    ADD CONSTRAINT company_sponsorships_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- TOC entry 5755 (class 2606 OID 889497)
-- Name: company_trading_names company_trading_names_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.company_trading_names
    ADD CONSTRAINT company_trading_names_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.companies(id) ON DELETE CASCADE;


--
-- TOC entry 5725 (class 2606 OID 888050)
-- Name: device_tokens device_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.device_tokens
    ADD CONSTRAINT device_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5754 (class 2606 OID 889473)
-- Name: email_templates email_templates_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.email_templates
    ADD CONSTRAINT email_templates_matter_id_foreign FOREIGN KEY (matter_id) REFERENCES public.matters(id) ON DELETE CASCADE;


--
-- TOC entry 5748 (class 2606 OID 889420)
-- Name: lead_matter_references lead_matter_references_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5749 (class 2606 OID 889410)
-- Name: lead_matter_references lead_matter_references_lead_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5750 (class 2606 OID 889415)
-- Name: lead_matter_references lead_matter_references_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_matter_id_foreign FOREIGN KEY (matter_id) REFERENCES public.matters(id) ON DELETE CASCADE;


--
-- TOC entry 5751 (class 2606 OID 889425)
-- Name: lead_matter_references lead_matter_references_updated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_matter_references
    ADD CONSTRAINT lead_matter_references_updated_by_foreign FOREIGN KEY (updated_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5752 (class 2606 OID 889447)
-- Name: lead_reminders lead_reminders_lead_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_lead_id_foreign FOREIGN KEY (lead_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5753 (class 2606 OID 889452)
-- Name: lead_reminders lead_reminders_reminded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lead_reminders
    ADD CONSTRAINT lead_reminders_reminded_by_foreign FOREIGN KEY (reminded_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5746 (class 2606 OID 889384)
-- Name: matter_reminders matter_reminders_client_matter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_client_matter_id_foreign FOREIGN KEY (client_matter_id) REFERENCES public.client_matters(id) ON DELETE CASCADE;


--
-- TOC entry 5747 (class 2606 OID 889389)
-- Name: matter_reminders matter_reminders_reminded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.matter_reminders
    ADD CONSTRAINT matter_reminders_reminded_by_foreign FOREIGN KEY (reminded_by) REFERENCES public.staff(id) ON DELETE SET NULL;


--
-- TOC entry 5741 (class 2606 OID 889025)
-- Name: message_attachments message_attachments_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_attachments
    ADD CONSTRAINT message_attachments_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- TOC entry 5729 (class 2606 OID 888185)
-- Name: message_recipients message_recipients_message_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.message_recipients
    ADD CONSTRAINT message_recipients_message_id_foreign FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE;


--
-- TOC entry 5726 (class 2606 OID 888074)
-- Name: refresh_tokens refresh_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.refresh_tokens
    ADD CONSTRAINT refresh_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.admins(id) ON DELETE CASCADE;


--
-- TOC entry 5738 (class 2606 OID 888652)
-- Name: staff staff_office_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_office_id_foreign FOREIGN KEY (office_id) REFERENCES public.branches(id) ON DELETE SET NULL;


--
-- TOC entry 5739 (class 2606 OID 888657)
-- Name: staff staff_role_foreign; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.staff
    ADD CONSTRAINT staff_role_foreign FOREIGN KEY (role) REFERENCES public.user_roles(id) ON DELETE SET NULL;


-- Completed on 2026-04-03 17:05:08

--
-- PostgreSQL database dump complete
--

\unrestrict iDcV0NIoSjKk5PdGPKGtqAcVsOdBMMwbcNeR6BehGabJ8JZszroAzZGzvkmFMRL

