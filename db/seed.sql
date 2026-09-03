-- Demo data for local development. Applied automatically by install.php.
-- Demo teacher login: teacher@example.com / Password123!
-- Demo admin login:   admin@example.com / AdminPass123!

INSERT INTO categories (name, slug, description, icon) VALUES
  ('Elementary', 'elementary', 'K-5 classroom resources', 'BookOpen'),
  ('Middle School', 'middle-school', 'Grades 6-8 resources', 'GraduationCap'),
  ('High School', 'high-school', 'Grades 9-12 resources', 'School'),
  ('Early Childhood', 'early-childhood', 'Pre-K and early learning', 'Baby'),
  ('Special Education (SPED)', 'special-education', 'Adaptive and SPED materials', 'HeartHandshake'),
  ('STEM & Robotics', 'stem-robotics', 'Hands-on STEM and robotics kits', 'FlaskConical'),
  ('Books & Leveled Literacy', 'books-leveled-literacy', 'Classroom libraries and readers', 'Library'),
  ('Arts & Crafts', 'arts-crafts', 'Art supplies and craft kits', 'Palette'),
  ('Classroom Décor', 'classroom-decor', 'Bulletin boards, borders, themes', 'LayoutGrid'),
  ('Teacher Planners', 'teacher-planners', 'Planners and organization tools', 'NotebookPen'),
  ('Classroom Supplies', 'classroom-supplies', 'General classroom consumables', 'Boxes'),
  ('Educational Games', 'educational-games', 'Learning games and manipulatives', 'Puzzle'),
  ('School Furniture', 'school-furniture', 'Desks, chairs, storage', 'Armchair'),
  ('Curriculum Units', 'curriculum-units', 'Full curriculum and unit plans', 'FileStack'),
  ('Multi-Item Bundles', 'multi-item-bundles', 'Bundled listings across categories', 'PackagePlus')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (name, email, password_hash, role, is_verified, school_name, district, state)
VALUES ('Megan Franneice', 'teacher@example.com', '$2a$12$X3Vuy2Wt8/kGh9wTyJ2bEO/ZWFVkEDQiuqEZpAaKwk7Z3kByO.SZa', 'teacher', 1, 'Pennsylvania Elementary', 'Oklahoma City Public Schools', 'OK')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO users (name, email, password_hash, role, is_verified)
VALUES ('Site Admin', 'admin@example.com', '$2a$12$WH6SEXck/tPmOMQ9PSrUzO647PRaQ7rnNMn5dlwQfBpCeK/ml7.I.', 'admin', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO email_templates (template_key, subject, html_body) VALUES
  ('welcome', 'Welcome to {{site_name}}!', '<p>Hi {{teacher_name}}, welcome aboard!</p>'),
  ('order_confirmation', 'Order #{{order_id}} confirmed', '<p>Thanks for your order! Total: ${{total}}</p>'),
  ('item_sold', 'You sold an item!', '<p>An item from order #{{order_id}} sold — prepare it for shipment.</p>'),
  ('shipping_confirmation', 'Your order has shipped', '<p>Order #{{order_id}} is on its way. Tracking: {{tracking_url}}</p>'),
  ('verification_approved', 'You''re verified!', '<p>Hi {{teacher_name}}, your educator verification was approved.</p>'),
  ('verification_rejected', 'Verification update', '<p>Hi {{teacher_name}}, we couldn''t verify your account: {{reason}}</p>'),
  ('dispute_resolution', 'Dispute #{{dispute_id}} resolved', '<p>Resolution: {{resolution}}</p>'),
  ('password_reset', 'Reset your {{site_name}} password', '<p>Hi {{name}}, click the link below to set a new password. This link expires in 1 hour.</p><p><a href="{{reset_url}}">{{reset_url}}</a></p><p>If you didn''t request this, you can safely ignore this email.</p>'),
  ('email_verification', 'Confirm your email for {{site_name}}', '<p>Hi {{name}}, thanks for joining {{site_name}}! Please confirm your email address to finish setting up your account.</p><p><a href="{{verify_url}}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;">Verify Email Address</a></p><p style="font-size:13px;color:#64748b;">Button not working? Go to My Account on the site and enter this code instead:</p><p style="font-size:26px;font-weight:700;letter-spacing:.15em;color:#0f172a;">{{code}}</p><p style="font-size:12px;color:#64748b;">This link and code expire in 24 hours. If you didn''t create this account, you can ignore this email.</p>'),
  ('donation_receipt', 'Thank you for your donation!', '<p>Hi {{donor_name}}, thank you for your ${{amount}} donation.</p>'),
  ('drip_teacher_getting_started', 'Ready to post your first listing, {{teacher_name}}?', '<p>Hi {{teacher_name}}, listing your first item on {{site_name}} takes less than 5 minutes and it''s always free to post. <a href="https://{{site_name}}/post-listing.php">Post a listing</a></p>'),
  ('drip_teacher_checkin', 'A few tips to help your listings sell', '<p>Hi {{teacher_name}}, listings with clear photos and a fair price sell fastest. Consider adding a photo or adjusting your price if something has been sitting a while.</p>'),
  ('drip_buyer_welcome_tips', 'Welcome! Here''s how to find great deals', '<p>Hi {{name}}, browse by category, filter by grade level, and save searches to your wishlist. Happy hunting!</p>'),
  ('drip_buyer_reengagement', 'Still looking for classroom supplies?', '<p>Hi {{name}}, new listings are posted daily on {{site_name}}. Come take another look!</p>'),
  ('drip_listing_tips', '3 tips to help your listing sell faster', '<p>Hi {{teacher_name}}, add multiple photos, write a detailed description, and price competitively to help your listing stand out.</p>'),
  ('drip_review_request', 'How did your order go?', '<p>Hi {{name}}, now that your order has arrived, would you leave the seller a quick review? It helps other teachers shop with confidence.</p>')
ON DUPLICATE KEY UPDATE subject = VALUES(subject);

INSERT INTO email_drips (name, trigger_event, is_enabled) VALUES
  ('New Teacher Welcome Series', 'teacher_registered', 1),
  ('New Buyer Welcome Series', 'buyer_registered', 1),
  ('Listing Posted Follow-up', 'listing_posted', 1),
  ('Post-Purchase Review Request', 'order_paid', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 1, 48, 'drip_teacher_getting_started' FROM email_drips WHERE name = 'New Teacher Welcome Series'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 2, 168, 'drip_teacher_checkin' FROM email_drips WHERE name = 'New Teacher Welcome Series'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 1, 24, 'drip_buyer_welcome_tips' FROM email_drips WHERE name = 'New Buyer Welcome Series'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 2, 120, 'drip_buyer_reengagement' FROM email_drips WHERE name = 'New Buyer Welcome Series'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 1, 72, 'drip_listing_tips' FROM email_drips WHERE name = 'Listing Posted Follow-up'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);
INSERT INTO email_drip_steps (drip_id, step_order, delay_hours, template_key)
SELECT id, 1, 120, 'drip_review_request' FROM email_drips WHERE name = 'Post-Purchase Review Request'
ON DUPLICATE KEY UPDATE template_key = VALUES(template_key);

INSERT INTO listings (seller_id, category_id, title, slug, description, price, grade_level, condition_type, shipping_type, shipping_fee, is_active)
SELECT u.id, c.id, 'Leveled Classroom Library Bundle (120 Books)', 'leveled-classroom-library-bundle-demo1',
  'A curated 120-book leveled library covering guided reading levels A-Z. Great for small group instruction.',
  145.00, '2nd-4th', 'good', 'both', 12.50, 1
FROM users u, categories c WHERE u.email = 'teacher@example.com' AND c.slug = 'books-leveled-literacy'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO listings (seller_id, category_id, title, slug, description, price, grade_level, condition_type, shipping_type, shipping_fee, is_active)
SELECT u.id, c.id, 'Robotics STEM Kit Class Set (6 Kits)', 'robotics-stem-kit-class-set-demo2',
  'Six hands-on robotics kits for small-group STEM stations. Lightly used, all pieces accounted for.',
  210.00, '4th-8th', 'like_new', 'local_pickup', 0.00, 1
FROM users u, categories c WHERE u.email = 'teacher@example.com' AND c.slug = 'stem-robotics'
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO listings (seller_id, category_id, title, slug, description, price, grade_level, condition_type, shipping_type, shipping_fee, is_active)
SELECT u.id, c.id, 'Calm-Down Corner SEL Furniture Set', 'calm-down-corner-sel-furniture-demo3',
  'Cushions, sensory tools, and a small bookshelf for a classroom regulation corner.',
  0.00, 'K-5', 'good', 'local_pickup', 0.00, 1
FROM users u, categories c WHERE u.email = 'teacher@example.com' AND c.slug = 'classroom-supplies'
ON DUPLICATE KEY UPDATE title = VALUES(title);
