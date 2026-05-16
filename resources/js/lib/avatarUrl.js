/** URL аватара пользователя для img src */
export function resolveAvatarUrl(avatar) {
  if (!avatar) return null;
  if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) {
    return avatar;
  }
  if (avatar.startsWith('storage/')) {
    return `/${avatar}`;
  }
  return `/${avatar}`;
}
