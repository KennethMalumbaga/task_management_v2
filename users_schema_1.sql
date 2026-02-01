--
-- PostgreSQL database dump
--

\restrict ZemRKOcwWlWYRiJcFpfSr95O1xmxPResbMDGXVYbZWd0NJlc3NTVuxIpXYDRySs

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
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    full_name character varying(50) NOT NULL,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    role text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    bio text,
    phone character varying(20) DEFAULT NULL::character varying,
    address text,
    skills text,
    profile_image character varying(255) DEFAULT 'default.png'::character varying,
    must_change_password boolean DEFAULT false,
    CONSTRAINT users_role_check CHECK ((role = ANY (ARRAY['admin'::text, 'employee'::text])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- PostgreSQL database dump complete
--

\unrestrict ZemRKOcwWlWYRiJcFpfSr95O1xmxPResbMDGXVYbZWd0NJlc3NTVuxIpXYDRySs

