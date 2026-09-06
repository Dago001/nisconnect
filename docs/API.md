# NISconnect — API Reference (v1)

Base URL `/api/v1`. JSON only. Bearer (Sanctum) auth except the public onboarding
endpoints. Errors use a stable envelope `{ "message": "...", "errors": { field: [..] } }`
with no internal detail. Onboarding, OTP, login and directory routes are rate limited.

## Auth & onboarding (public)
| Method | Path | Body | Notes |
|--------|------|------|-------|
| POST | `/auth/verify-service-number` | `{ service_number }` | Digits only. Returns `{verified, verification_id, record}` or generic `{verified:false}` (anti-enumeration). |
| POST | `/auth/confirm-identity` | `{ verification_id, phone }` | Sends OTP. |
| POST | `/auth/resend-otp` | `{ verification_id }` | Resend delay + cap enforced. |
| POST | `/auth/verify-otp` | `{ verification_id, code }` | Server-side verify. |
| POST | `/auth/set-credentials` | `{ verification_id, pin, password?, device{name,platform,...} }` | Creates account + device, returns `access_token`. |
| POST | `/auth/login` | `{ service_number, pin, device{name,platform} }` | Returns `access_token`. |

## Auth (bearer)
| Method | Path | Notes |
|--------|------|-------|
| POST | `/auth/logout` | Revokes current token. |
| GET | `/users/me` | Current officer + personnel fields. |
| PATCH | `/users/me` | Update display name. |
| PUT | `/users/me/privacy` | Per-key `everyone|contacts|nobody`. |

## Directory (bearer, rate limited)
| GET | `/directory/search?q=&directorate=&department=&command=&rank=&per_page=` | At least one filter required; capped results. |
| GET | `/directory/{serviceNumber}` | Single officer card. |

## Chats & messages (bearer)
| GET | `/chats` | Conversations for the user. |
| POST | `/chats` `{service_number}` | Start/resume a direct chat. |
| GET | `/chats/{conversation}` | Conversation + members. |
| GET | `/chats/{conversation}/messages?cursor=` | Cursor-paginated (newest first) + `next_cursor`. |
| POST | `/chats/{conversation}/messages` `{type,body,reply_to_id?,attachments[]}` | Send. |
| POST | `/chats/{conversation}/typing` | Broadcast typing. |
| GET | `/messages/search?q=&conversation_id?&type?` | Full-text search (GIN index) scoped to the user's conversations. |
| POST | `/messages/{message}/read` | Read receipt. |
| POST | `/messages/{message}/react` `{emoji}` | React. |
| DELETE | `/messages/{message}` | Delete own message. |

## Groups (bearer)
| GET | `/groups` · POST | `/groups` `{name,description?,members[]}` | List / create. |
| POST | `/groups/{group}/members` `{members[]}` | Admin-gated add. |
| DELETE | `/groups/{group}/members/{userId}` | Admin-gated remove. |
| POST | `/groups/{group}/leave` | Leave. |

## Media (bearer)
| POST | `/media` (multipart `file`, `kind`) | Private upload; MIME/ext/size validated. Returns `{id, download_url}`. |
| GET | `/media/{media}` | Authenticated, ownership/membership-checked download. |

## Calls (bearer — LiveKit signalling)
| POST | `/calls` `{conversation_id,type:voice|video}` | Initiate; returns `{call, token, livekit_url}`. Server mints the LiveKit JWT. |
| GET | `/calls/{call}/token` | Mint a join token for an authorised participant. |
| POST | `/calls/{call}/answer` | Answer; returns a token. |
| POST | `/calls/{call}/decline` · `/calls/{call}/end` | Decline / end. |
| GET | `/calls/history` | Paginated call history. |

## Channels (bearer — official announcements)
| GET | `/channels` · `/channels/{channel}` | List (with subscribed flag) / show. |
| GET | `/channels/{channel}/posts` | Cursor-paginated posts. |
| POST | `/channels/{channel}/posts` `{body}` | Publish (publisher role only). |
| POST | `/channels/{channel}/subscribe` | Subscribe. |

## Safety (bearer)
| POST | `/safety/block` `{user_id}` · `/safety/unblock` `{user_id}` | Block / unblock. |
| POST | `/safety/report` `{target_type,target_id,reason,details?}` | File a report. |

## Notifications (bearer)
| GET | `/notifications` | Paginated + `unread` count. |
| POST | `/notifications/{notification}/read` · `/notifications/read-all` | Mark read. |

## Devices (bearer)
| GET | `/devices` · DELETE `/devices/{device}` · DELETE `/devices/all` | List / revoke one / revoke others. |

## Realtime (WebSocket / Reverb)
Private channel `conversation.{id}` (members only) emits `message.new`, `message.read`,
`user.typing`. Private channel `user.{id}` for personal events, including call signalling
(`call.incoming`, `call.answered`, `call.declined`, `call.ended`). Channel auth via
`routes/channels.php`.

## OpenAPI
An OpenAPI 3 description can be generated from the Form Requests + Resources; wire
`php artisan l5-swagger:generate` (or scramble) in the release step to publish
`/docs`. The table above is the authoritative contract until then.
