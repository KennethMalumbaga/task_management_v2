--
-- PostgreSQL database dump
--

\restrict XZYYkgP6DmaXt0SIzIIkaaGyrS3BVAaLTb3kUiOaxfKjAkkvET7L8YNERCpKFmF

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2026-02-13 10:53:25

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
-- TOC entry 219 (class 1259 OID 17474)
-- Name: attendance; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance (
    id integer NOT NULL,
    user_id integer,
    att_date date,
    total_hours numeric(5,2) DEFAULT 0,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    time_in time without time zone,
    time_out time without time zone,
    last_heartbeat_at timestamp without time zone
);


ALTER TABLE public.attendance OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 17480)
-- Name: attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_id_seq OWNER TO postgres;

--
-- TOC entry 5230 (class 0 OID 0)
-- Dependencies: 220
-- Name: attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_id_seq OWNED BY public.attendance.id;


--
-- TOC entry 221 (class 1259 OID 17481)
-- Name: chat_attachments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.chat_attachments (
    attachment_id integer NOT NULL,
    chat_id integer NOT NULL,
    attachment_name character varying(255) NOT NULL
);


ALTER TABLE public.chat_attachments OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 17487)
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.chat_attachments_attachment_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chat_attachments_attachment_id_seq OWNER TO postgres;

--
-- TOC entry 5231 (class 0 OID 0)
-- Dependencies: 222
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.chat_attachments_attachment_id_seq OWNED BY public.chat_attachments.attachment_id;


--
-- TOC entry 223 (class 1259 OID 17488)
-- Name: chats; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.chats (
    chat_id integer NOT NULL,
    sender_id integer NOT NULL,
    receiver_id integer NOT NULL,
    message text NOT NULL,
    opened boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.chats OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 17499)
-- Name: chats_chat_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.chats_chat_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.chats_chat_id_seq OWNER TO postgres;

--
-- TOC entry 5232 (class 0 OID 0)
-- Dependencies: 224
-- Name: chats_chat_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.chats_chat_id_seq OWNED BY public.chats.chat_id;


--
-- TOC entry 225 (class 1259 OID 17500)
-- Name: group_members; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_members (
    id integer NOT NULL,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    role text DEFAULT 'member'::text,
    created_at timestamp without time zone DEFAULT now(),
    CONSTRAINT group_members_role_check CHECK ((role = ANY (ARRAY['leader'::text, 'member'::text])))
);


ALTER TABLE public.group_members OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 17511)
-- Name: group_members_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_members_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_members_id_seq OWNER TO postgres;

--
-- TOC entry 5233 (class 0 OID 0)
-- Dependencies: 226
-- Name: group_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_members_id_seq OWNED BY public.group_members.id;


--
-- TOC entry 227 (class 1259 OID 17512)
-- Name: group_message_attachments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_message_attachments (
    id integer NOT NULL,
    message_id integer NOT NULL,
    attachment_name text NOT NULL,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.group_message_attachments OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 17521)
-- Name: group_message_attachments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_message_attachments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_message_attachments_id_seq OWNER TO postgres;

--
-- TOC entry 5234 (class 0 OID 0)
-- Dependencies: 228
-- Name: group_message_attachments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_message_attachments_id_seq OWNED BY public.group_message_attachments.id;


--
-- TOC entry 229 (class 1259 OID 17522)
-- Name: group_message_reads; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_message_reads (
    id integer NOT NULL,
    group_id integer NOT NULL,
    user_id integer NOT NULL,
    last_message_id integer NOT NULL
);


ALTER TABLE public.group_message_reads OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 17529)
-- Name: group_message_reads_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_message_reads_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_message_reads_id_seq OWNER TO postgres;

--
-- TOC entry 5235 (class 0 OID 0)
-- Dependencies: 230
-- Name: group_message_reads_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_message_reads_id_seq OWNED BY public.group_message_reads.id;


--
-- TOC entry 231 (class 1259 OID 17530)
-- Name: group_messages; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.group_messages (
    id integer NOT NULL,
    group_id integer NOT NULL,
    sender_id integer NOT NULL,
    message text,
    created_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.group_messages OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 17539)
-- Name: group_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.group_messages_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.group_messages_id_seq OWNER TO postgres;

--
-- TOC entry 5236 (class 0 OID 0)
-- Dependencies: 232
-- Name: group_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.group_messages_id_seq OWNED BY public.group_messages.id;


--
-- TOC entry 233 (class 1259 OID 17540)
-- Name: groups; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.groups (
    id integer NOT NULL,
    name text NOT NULL,
    created_by integer,
    created_at timestamp without time zone DEFAULT now(),
    type text DEFAULT 'group'::text,
    task_id integer
);


ALTER TABLE public.groups OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 17549)
-- Name: groups_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.groups_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.groups_id_seq OWNER TO postgres;

--
-- TOC entry 5237 (class 0 OID 0)
-- Dependencies: 234
-- Name: groups_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.groups_id_seq OWNED BY public.groups.id;


--
-- TOC entry 250 (class 1259 OID 17800)
-- Name: leader_feedback; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.leader_feedback (
    id bigint NOT NULL,
    task_id integer NOT NULL,
    leader_id integer NOT NULL,
    member_id integer NOT NULL,
    rating smallint NOT NULL,
    comment text,
    created_at timestamp without time zone DEFAULT now() NOT NULL,
    updated_at timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT leader_feedback_rating_check CHECK (((rating >= 1) AND (rating <= 5)))
);


ALTER TABLE public.leader_feedback OWNER TO postgres;

--
-- TOC entry 249 (class 1259 OID 17799)
-- Name: leader_feedback_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.leader_feedback_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leader_feedback_id_seq OWNER TO postgres;

--
-- TOC entry 5238 (class 0 OID 0)
-- Dependencies: 249
-- Name: leader_feedback_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.leader_feedback_id_seq OWNED BY public.leader_feedback.id;


--
-- TOC entry 235 (class 1259 OID 17550)
-- Name: notifications; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.notifications (
    id integer NOT NULL,
    message text NOT NULL,
    recipient integer,
    type character varying(50) NOT NULL,
    date date DEFAULT CURRENT_DATE,
    is_read boolean DEFAULT false,
    task_id integer
);


ALTER TABLE public.notifications OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 17560)
-- Name: notifications_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.notifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.notifications_id_seq OWNER TO postgres;

--
-- TOC entry 5239 (class 0 OID 0)
-- Dependencies: 236
-- Name: notifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.notifications_id_seq OWNED BY public.notifications.id;


--
-- TOC entry 237 (class 1259 OID 17561)
-- Name: password_resets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.password_resets (
    id integer NOT NULL,
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    expires_at timestamp without time zone NOT NULL
);


ALTER TABLE public.password_resets OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 17571)
-- Name: password_resets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.password_resets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.password_resets_id_seq OWNER TO postgres;

--
-- TOC entry 5240 (class 0 OID 0)
-- Dependencies: 238
-- Name: password_resets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.password_resets_id_seq OWNED BY public.password_resets.id;


--
-- TOC entry 239 (class 1259 OID 17572)
-- Name: screenshots; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.screenshots (
    id integer NOT NULL,
    user_id integer,
    attendance_id integer,
    image_path character varying(255) NOT NULL,
    taken_at timestamp without time zone NOT NULL
);


ALTER TABLE public.screenshots OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 17578)
-- Name: screenshots_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.screenshots_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.screenshots_id_seq OWNER TO postgres;

--
-- TOC entry 5241 (class 0 OID 0)
-- Dependencies: 240
-- Name: screenshots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.screenshots_id_seq OWNED BY public.screenshots.id;


--
-- TOC entry 241 (class 1259 OID 17579)
-- Name: subtasks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.subtasks (
    id integer NOT NULL,
    task_id integer NOT NULL,
    member_id integer NOT NULL,
    description text NOT NULL,
    due_date date NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying,
    submission_file character varying(255),
    feedback text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    submission_note text,
    score smallint,
    CONSTRAINT subtasks_score_check CHECK (((score >= 1) AND (score <= 5))),
    CONSTRAINT subtasks_status_check CHECK (((status)::text = ANY (ARRAY[('pending'::character varying)::text, ('submitted'::character varying)::text, ('completed'::character varying)::text, ('revise'::character varying)::text])))
);


ALTER TABLE public.subtasks OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 17594)
-- Name: subtasks_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.subtasks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.subtasks_id_seq OWNER TO postgres;

--
-- TOC entry 5242 (class 0 OID 0)
-- Dependencies: 242
-- Name: subtasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.subtasks_id_seq OWNED BY public.subtasks.id;


--
-- TOC entry 243 (class 1259 OID 17595)
-- Name: task_assignees; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.task_assignees (
    id integer NOT NULL,
    task_id integer,
    user_id integer,
    role text DEFAULT 'member'::text,
    assigned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    performance_rating smallint,
    rating_comment text,
    rated_by integer,
    rated_at timestamp without time zone,
    CONSTRAINT task_assignees_performance_rating_check CHECK (((performance_rating >= 1) AND (performance_rating <= 5))),
    CONSTRAINT task_assignees_role_check CHECK ((role = ANY (ARRAY['leader'::text, 'member'::text])))
);


ALTER TABLE public.task_assignees OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 17604)
-- Name: task_assignees_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.task_assignees_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.task_assignees_id_seq OWNER TO postgres;

--
-- TOC entry 5243 (class 0 OID 0)
-- Dependencies: 244
-- Name: task_assignees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.task_assignees_id_seq OWNED BY public.task_assignees.id;


--
-- TOC entry 245 (class 1259 OID 17605)
-- Name: tasks; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tasks (
    id integer NOT NULL,
    title character varying(100) NOT NULL,
    description text,
    assigned_to integer,
    status text DEFAULT 'pending'::text,
    submission_file character varying(255),
    template_file character varying(255),
    review_comment text,
    reviewed_by integer,
    reviewed_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    due_date date NOT NULL,
    submission_note text,
    rating integer DEFAULT 0,
    leader_rating smallint,
    leader_review_comment text,
    CONSTRAINT tasks_leader_rating_check CHECK (((leader_rating >= 1) AND (leader_rating <= 5))),
    CONSTRAINT tasks_status_check CHECK ((status = ANY (ARRAY['pending'::text, 'in_progress'::text, 'completed'::text, 'rejected'::text, 'revise'::text])))
);


ALTER TABLE public.tasks OWNER TO postgres;

--
-- TOC entry 246 (class 1259 OID 17617)
-- Name: tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tasks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tasks_id_seq OWNER TO postgres;

--
-- TOC entry 5244 (class 0 OID 0)
-- Dependencies: 246
-- Name: tasks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tasks_id_seq OWNED BY public.tasks.id;


--
-- TOC entry 247 (class 1259 OID 17618)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    full_name character varying(50) NOT NULL,
    username character varying(50) NOT NULL,
    password character varying(255) NOT NULL,
    role text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    phone character varying(20) DEFAULT NULL::character varying,
    address text,
    skills text,
    profile_image character varying(255) DEFAULT 'default.png'::character varying,
    must_change_password boolean DEFAULT false,
    bio text,
    CONSTRAINT users_role_check CHECK ((role = ANY (ARRAY['admin'::text, 'employee'::text])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 248 (class 1259 OID 17633)
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- TOC entry 5245 (class 0 OID 0)
-- Dependencies: 248
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- TOC entry 4931 (class 2604 OID 17634)
-- Name: attendance id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance ALTER COLUMN id SET DEFAULT nextval('public.attendance_id_seq'::regclass);


--
-- TOC entry 4934 (class 2604 OID 17635)
-- Name: chat_attachments attachment_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments ALTER COLUMN attachment_id SET DEFAULT nextval('public.chat_attachments_attachment_id_seq'::regclass);


--
-- TOC entry 4935 (class 2604 OID 17636)
-- Name: chats chat_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chats ALTER COLUMN chat_id SET DEFAULT nextval('public.chats_chat_id_seq'::regclass);


--
-- TOC entry 4938 (class 2604 OID 17637)
-- Name: group_members id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members ALTER COLUMN id SET DEFAULT nextval('public.group_members_id_seq'::regclass);


--
-- TOC entry 4941 (class 2604 OID 17638)
-- Name: group_message_attachments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments ALTER COLUMN id SET DEFAULT nextval('public.group_message_attachments_id_seq'::regclass);


--
-- TOC entry 4943 (class 2604 OID 17639)
-- Name: group_message_reads id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_reads ALTER COLUMN id SET DEFAULT nextval('public.group_message_reads_id_seq'::regclass);


--
-- TOC entry 4944 (class 2604 OID 17640)
-- Name: group_messages id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages ALTER COLUMN id SET DEFAULT nextval('public.group_messages_id_seq'::regclass);


--
-- TOC entry 4946 (class 2604 OID 17641)
-- Name: groups id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups ALTER COLUMN id SET DEFAULT nextval('public.groups_id_seq'::regclass);


--
-- TOC entry 4971 (class 2604 OID 17803)
-- Name: leader_feedback id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.leader_feedback ALTER COLUMN id SET DEFAULT nextval('public.leader_feedback_id_seq'::regclass);


--
-- TOC entry 4949 (class 2604 OID 17642)
-- Name: notifications id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications ALTER COLUMN id SET DEFAULT nextval('public.notifications_id_seq'::regclass);


--
-- TOC entry 4952 (class 2604 OID 17643)
-- Name: password_resets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets ALTER COLUMN id SET DEFAULT nextval('public.password_resets_id_seq'::regclass);


--
-- TOC entry 4954 (class 2604 OID 17644)
-- Name: screenshots id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots ALTER COLUMN id SET DEFAULT nextval('public.screenshots_id_seq'::regclass);


--
-- TOC entry 4955 (class 2604 OID 17645)
-- Name: subtasks id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks ALTER COLUMN id SET DEFAULT nextval('public.subtasks_id_seq'::regclass);


--
-- TOC entry 4959 (class 2604 OID 17646)
-- Name: task_assignees id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees ALTER COLUMN id SET DEFAULT nextval('public.task_assignees_id_seq'::regclass);


--
-- TOC entry 4962 (class 2604 OID 17647)
-- Name: tasks id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks ALTER COLUMN id SET DEFAULT nextval('public.tasks_id_seq'::regclass);


--
-- TOC entry 4966 (class 2604 OID 17648)
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- TOC entry 5193 (class 0 OID 17474)
-- Dependencies: 219
-- Data for Name: attendance; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.attendance (id, user_id, att_date, total_hours, created_at, time_in, time_out) FROM stdin;
\.


--
-- TOC entry 5195 (class 0 OID 17481)
-- Dependencies: 221
-- Data for Name: chat_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.chat_attachments (attachment_id, chat_id, attachment_name) FROM stdin;
\.


--
-- TOC entry 5197 (class 0 OID 17488)
-- Dependencies: 223
-- Data for Name: chats; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.chats (chat_id, sender_id, receiver_id, message, opened, created_at) FROM stdin;
1	1	11	bruh	t	2026-02-12 00:08:34.221095
2	11	1	sir	t	2026-02-12 00:19:11.71629
3	1	11	jan	f	2026-02-13 02:07:32.444704
\.


--
-- TOC entry 5199 (class 0 OID 17500)
-- Dependencies: 225
-- Data for Name: group_members; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_members (id, group_id, user_id, role, created_at) FROM stdin;
1	1	8	leader	2026-02-11 22:36:59.82759
2	1	12	member	2026-02-11 22:36:59.830312
3	2	11	leader	2026-02-11 22:37:12.12798
4	2	9	member	2026-02-11 22:37:12.129054
5	2	10	member	2026-02-11 22:37:12.12947
6	3	8	leader	2026-02-11 22:38:15.149086
7	3	12	member	2026-02-11 22:38:15.149647
8	3	1	member	2026-02-11 22:38:15.149982
9	4	11	leader	2026-02-11 22:38:44.578618
10	4	10	member	2026-02-11 22:38:44.579854
11	4	9	member	2026-02-11 22:38:44.58026
12	4	1	member	2026-02-11 22:38:44.580646
\.


--
-- TOC entry 5201 (class 0 OID 17512)
-- Dependencies: 227
-- Data for Name: group_message_attachments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_message_attachments (id, message_id, attachment_name, created_at) FROM stdin;
\.


--
-- TOC entry 5203 (class 0 OID 17522)
-- Dependencies: 229
-- Data for Name: group_message_reads; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_message_reads (id, group_id, user_id, last_message_id) FROM stdin;
2	4	11	3
1	4	1	3
3	4	10	3
\.


--
-- TOC entry 5205 (class 0 OID 17530)
-- Dependencies: 231
-- Data for Name: group_messages; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.group_messages (id, group_id, sender_id, message, created_at) FROM stdin;
1	4	1	guys?	2026-02-12 00:08:46.1123
2	4	1	hello?	2026-02-12 00:08:48.811023
3	4	11	yes sir	2026-02-12 00:19:04.613332
\.


--
-- TOC entry 5207 (class 0 OID 17540)
-- Dependencies: 233
-- Data for Name: groups; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.groups (id, name, created_by, created_at, type, task_id) FROM stdin;
1	group 1	1	2026-02-11 22:36:59.818765	group	\N
2	group 2	1	2026-02-11 22:37:12.127025	group	\N
3	Task Management System	1	2026-02-11 22:38:15.14828	task_chat	1
4	E-Clinic System	1	2026-02-11 22:38:44.57751	task_chat	2
\.


--
-- TOC entry 5224 (class 0 OID 17800)
-- Dependencies: 250
-- Data for Name: leader_feedback; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.leader_feedback (id, task_id, leader_id, member_id, rating, comment, created_at, updated_at) FROM stdin;
1	2	11	10	5	Goods rapud kaayo	2026-02-12 23:06:26.634918	2026-02-12 23:07:02.998237
4	2	11	9	5	goods siya	2026-02-12 23:07:55.097185	2026-02-12 23:07:55.097185
5	1	8	12	5	goods ni siya sir, maatiman kaayo ang task	2026-02-13 00:57:11.680698	2026-02-13 00:57:11.680698
\.


--
-- TOC entry 5209 (class 0 OID 17550)
-- Dependencies: 235
-- Data for Name: notifications; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.notifications (id, message, recipient, type, date, is_read, task_id) FROM stdin;
1	'Task Management System' has been assigned to you as leader. Please review and start working on it	8	New Task Assigned	2026-02-11	f	1
2	'Task Management System' has been assigned to you. Please review and start working on it	12	New Task Assigned	2026-02-11	f	1
3	'E-Clinic System' has been assigned to you as leader. Please review and start working on it	11	New Task Assigned	2026-02-11	f	2
4	'E-Clinic System' has been assigned to you. Please review and start working on it	10	New Task Assigned	2026-02-11	f	2
5	'E-Clinic System' has been assigned to you. Please review and start working on it	9	New Task Assigned	2026-02-11	f	2
6	You have been assigned a subtask for: E-Clinic System	9	New Subtask	2026-02-12	f	2
7	You have been assigned a subtask for: E-Clinic System	10	New Subtask	2026-02-12	f	2
8	You have been assigned a subtask for: E-Clinic System	11	New Subtask	2026-02-12	f	2
9	Subtask submitted by User 11	11	Subtask Submitted	2026-02-12	f	2
10	Subtask submitted by User 10	11	Subtask Submitted	2026-02-12	f	2
11	Subtask submitted by User 9	11	Subtask Submitted	2026-02-12	f	2
12	Your subtask submission has been ACCEPTED. Score: 5/5.	11	Subtask Review	2026-02-12	f	2
13	Your subtask submission has been ACCEPTED. Score: 5/5.	10	Subtask Review	2026-02-12	f	2
14	Your subtask submission has been ACCEPTED. Score: 5/5.	9	Subtask Review	2026-02-12	f	2
15	Task Submitted by Leader (neljhan redondo)	1	Task Submitted	2026-02-12	f	2
16	Task Accepted & Rated (5/5): E-Clinic System	9	Task Verified	2026-02-12	f	2
17	Task Accepted & Rated (5/5): E-Clinic System	10	Task Verified	2026-02-12	f	2
18	Task Accepted & Rated (5/5): E-Clinic System	11	Task Verified	2026-02-12	f	2
19	Task Accepted & Rated (5/5): E-Clinic System	9	Task Verified	2026-02-13	f	2
20	Task Accepted & Rated (5/5): E-Clinic System	10	Task Verified	2026-02-13	f	2
21	Task Accepted & Rated (5/5): E-Clinic System	11	Task Verified	2026-02-13	f	2
22	You have been assigned a subtask for: Task Management System	12	New Subtask	2026-02-13	f	1
23	You have been assigned a subtask for: Task Management System	8	New Subtask	2026-02-13	f	1
24	Subtask submitted by User 12	8	Subtask Submitted	2026-02-13	f	1
25	Your subtask submission has been ACCEPTED. Score: 5/5.	12	Subtask Review	2026-02-13	f	1
26	Subtask submitted by User 8	8	Subtask Submitted	2026-02-13	f	1
27	Your subtask submission has been ACCEPTED. Self-rating is disabled.	8	Subtask Review	2026-02-13	f	1
28	Task Submitted by Leader (Kenneth Bryan Malumbaga)	1	Task Submitted	2026-02-13	f	1
29	Task Accepted & Rated (5/5): Task Management System	8	Task Verified	2026-02-13	f	1
30	Task Accepted & Rated (5/5): Task Management System	12	Task Verified	2026-02-13	f	1
\.


--
-- TOC entry 5211 (class 0 OID 17561)
-- Dependencies: 237
-- Data for Name: password_resets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.password_resets (id, email, token, created_at, expires_at) FROM stdin;
\.


--
-- TOC entry 5213 (class 0 OID 17572)
-- Dependencies: 239
-- Data for Name: screenshots; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.screenshots (id, user_id, attendance_id, image_path, taken_at) FROM stdin;
\.


--
-- TOC entry 5215 (class 0 OID 17579)
-- Dependencies: 241
-- Data for Name: subtasks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.subtasks (id, task_id, member_id, description, due_date, status, submission_file, feedback, created_at, updated_at, submission_note, score) FROM stdin;
3	2	11	Implement firebase password reset function with custom template and custom page	2026-02-13	completed	uploads/subtask_3_1770905800.jpg	para saakoa goods ni	2026-02-12 22:15:57.720444	2026-02-12 22:19:42.987625	Done	5
2	2	10	UI/UX design	2026-02-13	completed	uploads/subtask_2_1770905885.jpg	Good	2026-02-12 22:15:30.074561	2026-02-12 22:20:11.014832	here you goo	5
1	2	9	Implement firebase email and password auth	2026-02-14	completed	uploads/subtask_1_1770905943.png	good ka boi	2026-02-12 22:14:55.182513	2026-02-12 22:20:33.576559	goodsheesh boss	5
4	1	12	UI/UX design	2026-02-21	completed	uploads/subtask_4_1770915244.png	goods ni	2026-02-13 00:53:03.22524	2026-02-13 00:54:34.388893	goods	5
5	1	8	Implement firebase email and password auth	2026-02-21	completed	uploads/subtask_5_1770915316.png		2026-02-13 00:53:14.072824	2026-02-13 00:55:22.692208	okay na ni	\N
\.


--
-- TOC entry 5217 (class 0 OID 17595)
-- Dependencies: 243
-- Data for Name: task_assignees; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.task_assignees (id, task_id, user_id, role, assigned_at, performance_rating, rating_comment, rated_by, rated_at) FROM stdin;
2	1	12	member	2026-02-11 22:38:15.138549	\N	\N	\N	\N
4	2	10	member	2026-02-11 22:38:44.563968	\N	\N	\N	\N
5	2	9	member	2026-02-11 22:38:44.564454	\N	\N	\N	\N
3	2	11	leader	2026-02-11 22:38:44.562842	5	\N	1	2026-02-13 00:51:59.799642
1	1	8	leader	2026-02-11 22:38:15.137271	3	\N	1	2026-02-13 00:58:00.724405
\.


--
-- TOC entry 5219 (class 0 OID 17605)
-- Dependencies: 245
-- Data for Name: tasks; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tasks (id, title, description, assigned_to, status, submission_file, template_file, review_comment, reviewed_by, reviewed_at, created_at, due_date, submission_note, rating, leader_rating, leader_review_comment) FROM stdin;
2	E-Clinic System	Create E-Clinic System	11	completed	uploads/task_2_submit_1770906069.png	\N	nice guys	1	2026-02-13 00:51:59.778917	2026-02-11 22:38:44.561664	2026-03-14	goods nami sir	5	\N	\N
1	Task Management System	Create a task management system	8	completed	uploads/task_1_submit_1770915393.png	\N	nice ken	1	2026-02-13 00:58:00.707275	2026-02-11 22:38:15.134776	2026-03-14	goods ni sir	5	\N	\N
\.


--
-- TOC entry 5221 (class 0 OID 17618)
-- Dependencies: 247
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, full_name, username, password, role, created_at, phone, address, skills, profile_image, must_change_password, bio) FROM stdin;
1	Admin ako	admin	$2y$10$b/v2OHMZLbahxklajBoPguDE4JtJiSN4k84v4CCZSHZ8Bpd1MYbwS	admin	2026-01-31 10:55:22.536092	09123456789	sa lugar na wala ka	skill 1 ni ling	IMG-697ebe16f28190.33379626.jpg	f	\N
8	Kenneth Bryan Malumbaga	malumbaga.kennethbryan@dnsc.edu.ph	$2y$10$ZMqTCNmtpFvS3gXx4Xw0vuzd/I9tv1/M0N0CEgWE7uy1q1RnYcUem	employee	2026-02-11 22:25:13.990673	09702641643	Davao City	Web Developer	IMG-698e1596b41879.56473981.jpg	f	
12	mary zhane torrecampo	torrecampo.maryzhane@dnsc.edu.ph	$2y$10$ldKs3VmVUZNFGJdKq49a7ezDjxam7UDWFe9MXbyPTZcYNisOa6UM2	employee	2026-02-11 22:35:56.093871				IMG-698e15cc451b95.57089192.png	f	
9	Lorenz Laurente	laurente.lorenzmaikel@dnsc.edu.ph	$2y$10$rlbSPSTufi9fXmNHGvMrpelWyimyFd5N8aunKCFs1FQ7/W7h0Rwwy	employee	2026-02-11 22:32:42.762689				IMG-698e160de78e24.93447518.jpg	f	
10	kenshie maling	maling.kenshie@dnsc.edu.ph	$2y$10$efxlOnLPTaqP55kWwFEBGeWZmLpy1DjlKZ0ir7Np7uekxRRQ8r8/e	employee	2026-02-11 22:34:07.770259				IMG-698e1678d5d446.93340106.jpg	f	
11	neljhan redondo	redondo.neljhan@dnsc.edu.ph	$2y$10$6q/OPW/EogpiC4Qd2u/Qu.VoDRamTegeYddomWxy2rRsYFhc78CyG	employee	2026-02-11 22:34:56.034939				IMG-698e16a826e894.60947703.jpg	f	
\.


--
-- TOC entry 5246 (class 0 OID 0)
-- Dependencies: 220
-- Name: attendance_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.attendance_id_seq', 1, false);


--
-- TOC entry 5247 (class 0 OID 0)
-- Dependencies: 222
-- Name: chat_attachments_attachment_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.chat_attachments_attachment_id_seq', 1, false);


--
-- TOC entry 5248 (class 0 OID 0)
-- Dependencies: 224
-- Name: chats_chat_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.chats_chat_id_seq', 3, true);


--
-- TOC entry 5249 (class 0 OID 0)
-- Dependencies: 226
-- Name: group_members_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_members_id_seq', 12, true);


--
-- TOC entry 5250 (class 0 OID 0)
-- Dependencies: 228
-- Name: group_message_attachments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_message_attachments_id_seq', 1, false);


--
-- TOC entry 5251 (class 0 OID 0)
-- Dependencies: 230
-- Name: group_message_reads_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_message_reads_id_seq', 3, true);


--
-- TOC entry 5252 (class 0 OID 0)
-- Dependencies: 232
-- Name: group_messages_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.group_messages_id_seq', 3, true);


--
-- TOC entry 5253 (class 0 OID 0)
-- Dependencies: 234
-- Name: groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.groups_id_seq', 4, true);


--
-- TOC entry 5254 (class 0 OID 0)
-- Dependencies: 249
-- Name: leader_feedback_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.leader_feedback_id_seq', 5, true);


--
-- TOC entry 5255 (class 0 OID 0)
-- Dependencies: 236
-- Name: notifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.notifications_id_seq', 30, true);


--
-- TOC entry 5256 (class 0 OID 0)
-- Dependencies: 238
-- Name: password_resets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.password_resets_id_seq', 1, false);


--
-- TOC entry 5257 (class 0 OID 0)
-- Dependencies: 240
-- Name: screenshots_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.screenshots_id_seq', 1, false);


--
-- TOC entry 5258 (class 0 OID 0)
-- Dependencies: 242
-- Name: subtasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.subtasks_id_seq', 5, true);


--
-- TOC entry 5259 (class 0 OID 0)
-- Dependencies: 244
-- Name: task_assignees_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.task_assignees_id_seq', 5, true);


--
-- TOC entry 5260 (class 0 OID 0)
-- Dependencies: 246
-- Name: tasks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tasks_id_seq', 2, true);


--
-- TOC entry 5261 (class 0 OID 0)
-- Dependencies: 248
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 12, true);


--
-- TOC entry 4984 (class 2606 OID 17650)
-- Name: attendance attendance_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_pkey PRIMARY KEY (id);


--
-- TOC entry 4986 (class 2606 OID 17652)
-- Name: chat_attachments chat_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments
    ADD CONSTRAINT chat_attachments_pkey PRIMARY KEY (attachment_id);


--
-- TOC entry 4988 (class 2606 OID 17654)
-- Name: chats chats_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chats
    ADD CONSTRAINT chats_pkey PRIMARY KEY (chat_id);


--
-- TOC entry 4990 (class 2606 OID 17656)
-- Name: group_members group_members_group_user_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_group_user_key UNIQUE (group_id, user_id);


--
-- TOC entry 4992 (class 2606 OID 17658)
-- Name: group_members group_members_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT group_members_pkey PRIMARY KEY (id);


--
-- TOC entry 4994 (class 2606 OID 17660)
-- Name: group_message_attachments group_message_attachments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments
    ADD CONSTRAINT group_message_attachments_pkey PRIMARY KEY (id);


--
-- TOC entry 4996 (class 2606 OID 17662)
-- Name: group_message_reads group_message_reads_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_reads
    ADD CONSTRAINT group_message_reads_pkey PRIMARY KEY (id);


--
-- TOC entry 4998 (class 2606 OID 17664)
-- Name: group_messages group_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT group_messages_pkey PRIMARY KEY (id);


--
-- TOC entry 5000 (class 2606 OID 17666)
-- Name: groups groups_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_pkey PRIMARY KEY (id);


--
-- TOC entry 5023 (class 2606 OID 17817)
-- Name: leader_feedback leader_feedback_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.leader_feedback
    ADD CONSTRAINT leader_feedback_pkey PRIMARY KEY (id);


--
-- TOC entry 5025 (class 2606 OID 17819)
-- Name: leader_feedback leader_feedback_unique; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.leader_feedback
    ADD CONSTRAINT leader_feedback_unique UNIQUE (task_id, leader_id, member_id);


--
-- TOC entry 5003 (class 2606 OID 17668)
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- TOC entry 5005 (class 2606 OID 17670)
-- Name: password_resets password_resets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_pkey PRIMARY KEY (id);


--
-- TOC entry 5007 (class 2606 OID 17672)
-- Name: screenshots screenshots_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_pkey PRIMARY KEY (id);


--
-- TOC entry 5011 (class 2606 OID 17674)
-- Name: subtasks subtasks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_pkey PRIMARY KEY (id);


--
-- TOC entry 5013 (class 2606 OID 17676)
-- Name: task_assignees task_assignees_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_pkey PRIMARY KEY (id);


--
-- TOC entry 5015 (class 2606 OID 17678)
-- Name: task_assignees task_assignees_task_id_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_task_id_user_id_key UNIQUE (task_id, user_id);


--
-- TOC entry 5017 (class 2606 OID 17680)
-- Name: tasks tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_pkey PRIMARY KEY (id);


--
-- TOC entry 5019 (class 2606 OID 17682)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- TOC entry 5021 (class 2606 OID 17684)
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- TOC entry 5001 (class 1259 OID 17685)
-- Name: idx_groups_task_chat_task_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_groups_task_chat_task_id ON public.groups USING btree (task_id) WHERE (type = 'task_chat'::text);


--
-- TOC entry 5008 (class 1259 OID 17686)
-- Name: idx_subtasks_member_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subtasks_member_id ON public.subtasks USING btree (member_id);


--
-- TOC entry 5009 (class 1259 OID 17687)
-- Name: idx_subtasks_task_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_subtasks_task_id ON public.subtasks USING btree (task_id);


--
-- TOC entry 5026 (class 2606 OID 17688)
-- Name: attendance attendance_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance
    ADD CONSTRAINT attendance_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- TOC entry 5027 (class 2606 OID 17693)
-- Name: chat_attachments chat_attachments_chat_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.chat_attachments
    ADD CONSTRAINT chat_attachments_chat_id_fkey FOREIGN KEY (chat_id) REFERENCES public.chats(chat_id) ON DELETE CASCADE;


--
-- TOC entry 5028 (class 2606 OID 17698)
-- Name: group_members fk_group_members_group; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT fk_group_members_group FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- TOC entry 5029 (class 2606 OID 17703)
-- Name: group_members fk_group_members_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_members
    ADD CONSTRAINT fk_group_members_user FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5033 (class 2606 OID 17708)
-- Name: group_messages fk_group_messages_group; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT fk_group_messages_group FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- TOC entry 5034 (class 2606 OID 17713)
-- Name: group_messages fk_group_messages_sender; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_messages
    ADD CONSTRAINT fk_group_messages_sender FOREIGN KEY (sender_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5030 (class 2606 OID 17718)
-- Name: group_message_attachments fk_group_msg_attach_msg; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_attachments
    ADD CONSTRAINT fk_group_msg_attach_msg FOREIGN KEY (message_id) REFERENCES public.group_messages(id) ON DELETE CASCADE;


--
-- TOC entry 5031 (class 2606 OID 17723)
-- Name: group_message_reads group_message_reads_group_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_reads
    ADD CONSTRAINT group_message_reads_group_id_fkey FOREIGN KEY (group_id) REFERENCES public.groups(id) ON DELETE CASCADE;


--
-- TOC entry 5032 (class 2606 OID 17728)
-- Name: group_message_reads group_message_reads_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.group_message_reads
    ADD CONSTRAINT group_message_reads_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5035 (class 2606 OID 17733)
-- Name: groups groups_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.groups
    ADD CONSTRAINT groups_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- TOC entry 5036 (class 2606 OID 17738)
-- Name: notifications notifications_recipient_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_recipient_fkey FOREIGN KEY (recipient) REFERENCES public.users(id);


--
-- TOC entry 5037 (class 2606 OID 17743)
-- Name: notifications notifications_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE SET NULL;


--
-- TOC entry 5038 (class 2606 OID 17748)
-- Name: screenshots screenshots_attendance_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_attendance_id_fkey FOREIGN KEY (attendance_id) REFERENCES public.attendance(id);


--
-- TOC entry 5039 (class 2606 OID 17753)
-- Name: screenshots screenshots_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.screenshots
    ADD CONSTRAINT screenshots_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id);


--
-- TOC entry 5040 (class 2606 OID 17758)
-- Name: subtasks subtasks_member_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_member_id_fkey FOREIGN KEY (member_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5041 (class 2606 OID 17763)
-- Name: subtasks subtasks_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.subtasks
    ADD CONSTRAINT subtasks_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- TOC entry 5042 (class 2606 OID 17768)
-- Name: task_assignees task_assignees_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.tasks(id) ON DELETE CASCADE;


--
-- TOC entry 5043 (class 2606 OID 17773)
-- Name: task_assignees task_assignees_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.task_assignees
    ADD CONSTRAINT task_assignees_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- TOC entry 5044 (class 2606 OID 17778)
-- Name: tasks tasks_assigned_to_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_assigned_to_fkey FOREIGN KEY (assigned_to) REFERENCES public.users(id);


--
-- TOC entry 5045 (class 2606 OID 17783)
-- Name: tasks tasks_reviewed_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tasks
    ADD CONSTRAINT tasks_reviewed_by_fkey FOREIGN KEY (reviewed_by) REFERENCES public.users(id);


-- Completed on 2026-02-13 10:53:26

--
-- PostgreSQL database dump complete
--

\unrestrict XZYYkgP6DmaXt0SIzIIkaaGyrS3BVAaLTb3kUiOaxfKjAkkvET7L8YNERCpKFmF

