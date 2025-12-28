-- Add to existing schema or create new table
CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    excerpt VARCHAR(500),
    category VARCHAR(100),
    author VARCHAR(100),
    image VARCHAR(255),
    views INT DEFAULT 0,
    is_published BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add index for better performance
CREATE INDEX idx_news_published ON news(is_published, created_at);
CREATE INDEX idx_news_category ON news(category);